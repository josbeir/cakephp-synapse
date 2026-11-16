<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Protocol;

use Cake\Core\Configure;
use Crustum\Mcp\Enums\MetaKey;
use Crustum\Mcp\Server\Transport\StdioTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Documentation\SearchEngine;
use Synapse\Server\SynapseServer;
use Synapse\Test\TestCase\McpTestCase;

/**
 * Verifies the public catalog, JSON-RPC responses and native STDIO entry point.
 */
class McpSurfaceTest extends McpTestCase
{
    private SynapseServer $server;

    /**
     * @var resource
     */
    private $output;

    protected function setUp(): void
    {
        parent::setUp();
        $output = fopen('php://temp', 'w+');
        $this->assertIsResource($output);
        $this->output = $output;
        $this->server = new SynapseServer(new StdioTransport(output: $this->output));
        $this->server->start();
    }

    protected function tearDown(): void
    {
        fclose($this->output);
        parent::tearDown();
    }

    /**
     * @param string $method MCP method
     * @param array<string, mixed> $params Method parameters
     * @return array<string, mixed>
     */
    private function request(string $method, array $params = []): array
    {
        $this->server->handle(json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR));
        rewind($this->output);
        $line = fgets($this->output);
        $this->assertIsString($line);
        $response = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertFalse(fgets($this->output));
        ftruncate($this->output, 0);
        rewind($this->output);

        return $response;
    }

    /**
     * Read the complete catalog even when Crustum paginates it.
     *
     * @param string $method List method
     * @param string $key Result key
     * @return array<int, array<string, mixed>>
     */
    private function catalog(string $method, string $key): array
    {
        $items = [];
        $params = ['per_page' => 5];
        do {
            $response = $this->request($method, $params);
            $this->assertArrayNotHasKey('error', $response);
            $items = array_merge($items, $response['result'][$key]);
            $params['cursor'] = $response['result']['nextCursor'] ?? null;
        } while ($params['cursor'] !== null);

        return $items;
    }

    public function testInitializeAndCompleteCatalog(): void
    {
        $initialize = $this->request('initialize', ['protocolVersion' => '2025-11-25']);
        $this->assertSame('2025-11-25', $initialize['result']['protocolVersion']);
        $this->assertSame('Synapse MCP Server', $initialize['result']['serverInfo']['name']);
        $this->assertNotEmpty($initialize['result']['instructions']);

        $tools = $this->catalog('tools/list', 'tools');
        $this->assertEqualsCanonicalizing([
            'system_info',
            'config_read',
            'debug_status',
            'list_env_vars',
            'database_connections',
            'database_schema',
            'list_routes',
            'get_route',
            'match_url',
            'detect_route_collisions',
            'cache_configs',
            'cache_read',
            'cache_write',
            'cache_delete',
            'cache_clear',
            'list_commands',
            'get_command_info',
            'run_command',
            'log_list',
            'log_read',
            'orm_describe',
            'orm_find',
            'tinker',
            'search_docs',
            'docs_stats',
            'get_doc',
        ], array_column($tools, 'name'));
        foreach ($tools as $tool) {
            $this->assertNotEmpty($tool['description']);
            $this->assertSame('object', $tool['inputSchema']['type']);
        }

        $byName = array_column($tools, null, 'name');
        $this->assertTrue($byName['tinker']['annotations']['destructiveHint']);
        $this->assertFalse($byName['tinker']['annotations']['idempotentHint']);
        $this->assertTrue($byName['cache_write']['annotations']['idempotentHint']);
        $this->assertTrue($byName['system_info']['annotations']['readOnlyHint']);

        $this->assertEqualsCanonicalizing([
            'code-reviewer',
            'database-explorer',
            'debug-helper',
            'documentation-expert',
            'feature-builder',
            'migration-guide',
            'orm-query-helper',
            'performance-analyzer',
            'testing-assistant',
            'tinker-workshop',
            'quality-assurance',
        ], array_column($this->catalog('prompts/list', 'prompts'), 'name'));
        $templates = $this->catalog('resources/templates/list', 'resourceTemplates');
        $this->assertEqualsCanonicalizing(
            ['docs://search/{query}', 'docs://content/{docId}'],
            array_column($templates, 'uriTemplate'),
        );
        $this->assertSame(['text/markdown', 'text/markdown'], array_column($templates, 'mimeType'));
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function promptProvider(): array
    {
        return [
            'code-reviewer' => ['code-reviewer', ['code' => 'example']],
            'database-explorer' => ['database-explorer', ['table' => 'example']],
            'debug-helper' => ['debug-helper', ['error' => 'example']],
            'documentation-expert' => ['documentation-expert', ['topic' => 'example']],
            'feature-builder' => ['feature-builder', ['feature' => 'example']],
            'migration-guide' => ['migration-guide', ['fromVersion' => '4.5', 'toVersion' => '5.4']],
            'orm-query-helper' => ['orm-query-helper', ['queryGoal' => 'example']],
            'performance-analyzer' => ['performance-analyzer', ['concern' => 'example']],
            'testing-assistant' => ['testing-assistant', ['subject' => 'example']],
            'tinker-workshop' => ['tinker-workshop', ['goal' => 'explore']],
            'quality-assurance' => ['quality-assurance', []],
        ];
    }

    /**
     * @param string $name Prompt name
     * @param array<string, string> $arguments Valid required arguments
     */
    #[DataProvider('promptProvider')]
    public function testPromptInvocationAndValidation(string $name, array $arguments): void
    {
        $response = $this->request('prompts/get', ['name' => $name, 'arguments' => $arguments]);
        $this->assertArrayNotHasKey('error', $response);
        $this->assertSame('user', $response['result']['messages'][0]['role']);
        $this->assertNotEmpty($response['result']['messages'][0]['content']['text']);

        foreach ($arguments as $key => $value) {
            foreach ([[], ''] as $invalid) {
                $response = $this->request('prompts/get', [
                    'name' => $name,
                    'arguments' => array_replace($arguments, [$key => $invalid]),
                ]);
                $this->assertSame(-32602, $response['error']['code']);
                $this->assertStringContainsString($key, $response['error']['message']);
            }
        }
    }

    public function testNullableFiltersAdvertisePortableSchemas(): void
    {
        $tools = array_column($this->catalog('tools/list', 'tools'), null, 'name');
        $filtersByTool = [
            'database_schema' => ['table'],
            'list_commands' => ['plugin', 'namespace'],
            'list_routes' => ['method', 'plugin', 'prefix', 'controller'],
            'log_read' => ['level'],
        ];
        foreach ($filtersByTool as $name => $filters) {
            $schema = $tools[$name]['inputSchema'];
            foreach ($filters as $filter) {
                $property = $schema['properties'][$filter];
                $this->assertArrayNotHasKey('type', $property);
                $this->assertSame([['type' => 'string'], ['type' => 'null']], $property['anyOf']);
                $this->assertNotEmpty($property['description']);
                $this->assertNotContains($filter, $schema['required'] ?? []);
            }
        }

        foreach ($tools as $tool) {
            foreach ($tool['inputSchema']['properties'] as $property) {
                foreach ($property['anyOf'] ?? [$property] as $branch) {
                    $this->assertIsString($branch['type']);
                }
            }
        }
    }

    public function testToolInvocationErrorsAndRedaction(): void
    {
        $result = $this->request('tools/call', ['name' => 'system_info']);
        $payload = json_decode($result['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('cakephp_version', $payload);

        $invalid = $this->request('tools/call', ['name' => 'search_docs', 'arguments' => ['query' => []]]);
        $this->assertTrue($invalid['result']['isError']);
        $this->assertStringContainsString('query', $invalid['result']['content'][0]['text']);

        Configure::write('Synapse.test_secret', 'test-sensitive-value');
        $redacted = $this->request('tools/call', [
            'name' => 'config_read',
            'arguments' => ['key' => 'Synapse.test_secret'],
        ]);
        $this->assertArrayNotHasKey('error', $redacted);
        $this->assertSame('"[REDACTED]"', $redacted['result']['content'][0]['text']);
        $unknown = $this->request('tools/call', ['name' => 'unknown_tool']);
        $this->assertSame(-32602, $unknown['error']['code']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function uriValueProvider(): array
    {
        return [
            'spaces' => ['database queries'],
            'slash' => ['nested/page'],
            'unicode' => ['café'],
            'percent' => ['literal%2Fname'],
        ];
    }

    /**
     * @param string $value URI variable before encoding
     */
    #[DataProvider('uriValueProvider')]
    public function testEncodedResourcesDecodeOnce(string $value): void
    {
        $engine = $this->createMock(SearchEngine::class);
        $id = 'source::' . $value;
        $engine->expects($this->once())->method('getDocumentById')->with($id)->willReturn(['content' => '# Example']);
        $service = $this->createMock(DocumentSearchService::class);
        $service->expects($this->once())->method('search')
            ->with($value, ['limit' => 10, 'highlight' => true])->willReturn([]);
        $service->method('getSearchEngine')->willReturn($engine);
        $this->container->add(DocumentSearchService::class, $service, true);

        foreach (['search' => $value, 'content' => $id] as $kind => $argument) {
            $uri = 'docs://' . $kind . '/' . rawurlencode($argument);
            $response = $this->request('resources/read', ['uri' => $uri]);
            $this->assertArrayNotHasKey('error', $response);
            $this->assertSame($uri, $response['result']['contents'][0]['uri']);
            $this->assertSame('text/markdown', $response['result']['contents'][0]['mimeType']);
            $this->assertNotEmpty($response['result']['contents'][0]['text']);
        }
    }

    public function testMissingAndUnsupportedResourcesAreSafeErrors(): void
    {
        $service = $this->createStub(DocumentSearchService::class);
        $engine = $this->createStub(SearchEngine::class);
        $engine->method('getDocumentById')->willReturn(null);
        $service->method('getSearchEngine')->willReturn($engine);
        $this->container->add(DocumentSearchService::class, $service, true);

        $missing = $this->request('resources/read', ['uri' => 'docs://content/source%3A%3Amissing']);
        $this->assertArrayHasKey('error', $missing);
        $this->assertStringContainsString('Document not found', $missing['error']['message']);
        foreach (['file:///etc/passwd', 'docs://content/../../outside', 'docs://unknown/value'] as $uri) {
            $response = $this->request('resources/read', ['uri' => $uri]);
            $this->assertSame(-32602, $response['error']['code']);
        }
    }

    public function testModernDiscoveryAndRequests(): void
    {
        $meta = [
            MetaKey::PROTOCOL_VERSION->value => '2026-07-28',
            MetaKey::CLIENT_CAPABILITIES->value => [],
        ];
        $discovery = $this->request('server/discover', ['_meta' => $meta]);
        $this->assertContains('2026-07-28', $discovery['result']['supportedVersions']);
        $result = $this->request('tools/call', ['_meta' => $meta, 'name' => 'system_info']);
        $this->assertFalse($result['result']['isError']);
    }

    public function testNativeStdioCommandFreshAndRepeatedStartup(): void
    {
        $messages = [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-11-25']],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => ['per_page' => 50]],
            ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'list_commands']],
            [
                'jsonrpc' => '2.0', 'id' => 4, 'method' => 'prompts/get',
                'params' => ['name' => 'debug-helper', 'arguments' => ['error' => 'example']],
            ],
            [
                'jsonrpc' => '2.0', 'id' => 5, 'method' => 'resources/read',
                'params' => ['uri' => 'docs://search/caching'],
            ],
        ];
        $input = implode(PHP_EOL, array_map(
            fn(array $message): string => json_encode($message, JSON_THROW_ON_ERROR),
            $messages,
        ));
        foreach ([1, 2] as $run) {
            $process = new Process([PHP_BINARY, ROOT . DS . 'bin' . DS . 'cake.php', 'mcp', 'start', 'synapse'], ROOT);
            $process->setInput($input . PHP_EOL . "{invalid json}\n");
            $process->setTimeout(15);
            $process->run();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $responses = array_map(
                fn(string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
                array_filter(explode(PHP_EOL, trim($process->getOutput()))),
            );
            $this->assertCount(6, $responses);
            $this->assertCount(26, $responses[1]['result']['tools']);
            $commands = json_decode($responses[2]['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
            $this->assertContains('mcp start', array_column($commands['commands'], 'name'));
            $this->assertNotContains('synapse server', array_column($commands['commands'], 'name'));
            $this->assertNotEmpty($responses[3]['result']['messages']);
            $this->assertSame('text/markdown', $responses[4]['result']['contents'][0]['mimeType']);
            $this->assertSame(-32700, $responses[5]['error']['code']);
        }
    }
}
