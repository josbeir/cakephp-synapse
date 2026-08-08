<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Protocol;

use Cake\TestSuite\TestCase;
use Mcp\Server\Transport\StdioTransport;
use Synapse\Builder\ServerBuilder;

/**
 * Verifies the MCP wire surface exposed by the configured plugin discovery.
 */
class McpSurfaceTest extends TestCase
{
    /**
     * The server must advertise usable instructions and discover all built-in
     * capability categories through the protocol, not just construct a Server.
     */
    public function testInitializeAndCapabilityDiscovery(): void
    {
        $messages = [
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => ServerBuilder::DEFAULT_PROTOCOL_VERSION,
                    'capabilities' => [],
                    'clientInfo' => ['name' => 'synapse-test-client', 'version' => '1.0.0'],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'notifications/initialized',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 2,
                'method' => 'tools/list',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 3,
                'method' => 'prompts/list',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 4,
                'method' => 'resources/templates/list',
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 5,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'system_info',
                    'arguments' => [],
                ],
            ], JSON_THROW_ON_ERROR),
            json_encode([
                'jsonrpc' => '2.0',
                'id' => 6,
                'method' => 'prompts/get',
                'params' => [
                    'name' => 'debug-helper',
                    'arguments' => ['error' => 'A test error'],
                ],
            ], JSON_THROW_ON_ERROR),
        ];

        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');
        $this->assertIsResource($input);
        $this->assertIsResource($output);
        fwrite($input, implode(PHP_EOL, $messages) . PHP_EOL);
        rewind($input);

        $transport = new class ($input, $output) extends StdioTransport {
            public function close(): void
            {
                // Keep the output stream open so the test can inspect responses.
            }
        };

        $server = (new ServerBuilder())
            ->withoutCache()
            ->withPluginTools()
            ->build();

        $server->run($transport);

        rewind($output);
        $responses = [];
        while (($line = fgets($output)) !== false) {
            $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                $responses[] = $decoded;
            }
        }

        fclose($input);
        fclose($output);

        $responsesById = [];
        foreach ($responses as $response) {
            if (isset($response['id'])) {
                $responsesById[(int)$response['id']] = $response;
            }
        }

        $initialize = $responsesById[1]['result'] ?? [];
        $this->assertSame('Synapse MCP Server', $initialize['serverInfo']['name'] ?? null);
        $this->assertSame(ServerBuilder::DEFAULT_PROTOCOL_VERSION, $initialize['protocolVersion'] ?? null);
        $this->assertNotEmpty($initialize['instructions'] ?? null);

        $tools = $responsesById[2]['result']['tools'] ?? [];
        $toolNames = array_column($tools, 'name');
        $this->assertContains('search_docs', $toolNames);
        $this->assertContains('get_doc', $toolNames);
        $this->assertContains('tinker', $toolNames);
        $this->assertContains('run_command', $toolNames);

        $tinker = current(array_filter($tools, static fn(array $tool): bool => $tool['name'] === 'tinker'));
        $this->assertIsArray($tinker);
        $this->assertTrue($tinker['annotations']['destructiveHint'] ?? false);

        $prompts = $responsesById[3]['result']['prompts'] ?? [];
        $promptNames = array_column($prompts, 'name');
        $this->assertContains('debug-helper', $promptNames);
        $this->assertContains('documentation-expert', $promptNames);

        $templates = $responsesById[4]['result']['resourceTemplates'] ?? [];
        $templateUris = array_column($templates, 'uriTemplate');
        $this->assertContains('docs://search/{query}', $templateUris);
        $this->assertContains('docs://content/{docId}', $templateUris);

        $toolCall = $responsesById[5]['result']['content'][0]['text'] ?? null;
        $this->assertIsString($toolCall);
        $this->assertStringContainsString('cakephp_version', $toolCall);

        $prompt = $responsesById[6]['result']['messages'][0]['content']['text'] ?? null;
        $this->assertIsString($prompt);
        $this->assertStringContainsString('search_docs, get_doc, tinker', $prompt);
    }
}
