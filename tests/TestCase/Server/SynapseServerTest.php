<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Server;

use Cake\Core\Configure;
use Crustum\Mcp\Server\Transport\StdioTransport;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Synapse\Server\SynapseServer;
use Synapse\Test\TestCase\McpTestCase;
use Synapse\Tools\SystemInfoTool;
use TestApp\Mcp\CustomPrompt;
use TestApp\Mcp\CustomResource;
use TestApp\Mcp\CustomTool;
use TestApp\Mcp\GreetingService;
use TestApp\Mcp\HiddenTool;

/**
 * Tests explicit catalog configuration and host application extension points.
 */
class SynapseServerTest extends McpTestCase
{
    public function testMetadataConfiguration(): void
    {
        Configure::write('Synapse.serverInfo', ['name' => 'Custom server', 'version' => '2.0.0']);
        Configure::write('Synapse.instructions', 'Custom instructions');
        $server = new SynapseServer(new StdioTransport());
        $server->start();

        $context = $server->createContext();

        $this->assertSame('Custom server', $context->implementation->name);
        $this->assertSame('2.0.0', $context->implementation->version);
        $this->assertSame('Custom instructions', $context->instructions);
    }

    public function testCustomCapabilitiesUseContainerDependencies(): void
    {
        $this->container->addShared(GreetingService::class);
        foreach ([CustomTool::class, CustomPrompt::class, CustomResource::class] as $class) {
            $this->container->addShared($class)->addArgument(GreetingService::class);
        }

        Configure::write('Synapse.capabilities', [
            'tools' => [CustomTool::class, HiddenTool::class],
            'prompts' => [CustomPrompt::class],
            'resources' => [CustomResource::class],
        ]);

        SynapseServer::tool(CustomTool::class)->assertOk()->assertSee('Hello from the application container');
        SynapseServer::prompt(CustomPrompt::class)->assertOk()->assertSee('Hello from the application container');
        SynapseServer::resource(CustomResource::class)->assertOk()->assertSee('Hello from the application container');

        $server = new SynapseServer(new StdioTransport());
        $server->start();

        $context = $server->createContext();
        $this->assertCount(27, $context->tools());
        $this->assertCount(12, $context->prompts());
        $this->assertCount(1, $context->resources());
        $this->assertCount(2, $context->resourceTemplates());
        SynapseServer::tool(HiddenTool::class)->assertHasErrors();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidCatalogProvider(): array
    {
        return [
            'not a list' => ['invalid', 'must be a list'],
            'associative' => [['name' => CustomTool::class], 'must be a list'],
            'wrong category' => [[CustomResource::class], 'must contain subclasses'],
            'missing class' => [['App\\MissingTool'], 'must contain subclasses'],
            'non class' => [[42], 'must contain subclasses'],
            'duplicate builtin' => [[SystemInfoTool::class], 'Duplicate tools capability name'],
            'missing dependency' => [[CustomTool::class], 'Register its dependencies'],
        ];
    }

    /**
     * @param mixed $classes Invalid configured catalog
     * @param string $message Expected actionable error
     */
    #[DataProvider('invalidCatalogProvider')]
    public function testInvalidCatalogFailsAtStartup(mixed $classes, string $message): void
    {
        Configure::write('Synapse.capabilities.tools', $classes);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new SynapseServer(new StdioTransport()))->start();
    }

    public function testIncorrectContainerBindingFailsAtStartup(): void
    {
        $this->container->add(CustomTool::class, new GreetingService());
        Configure::write('Synapse.capabilities.tools', [CustomTool::class]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The container must resolve');

        (new SynapseServer(new StdioTransport()))->start();
    }
}
