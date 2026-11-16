<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase;

use Cake\Console\CommandCollection;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Synapse\Server\SynapseServer;
use Synapse\Service\CommandService;
use Synapse\SynapsePlugin;
use TestApp\Application;
use TestApp\Command\TestCommand;

/**
 * Tests CLI registration, configuration overrides and application-scoped commands.
 */
class SynapsePluginTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = [
            'Synapse' => Configure::read('Synapse'),
            'Mcp' => Configure::read('Mcp'),
        ];
        Configure::delete('Synapse');
        Configure::delete('Mcp');
    }

    protected function tearDown(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            Configure::delete($key);
            if ($value !== null) {
                Configure::write($key, $value);
            }
        }

        parent::tearDown();
    }

    public function testBootstrapPreservesHostOverridesAndOtherServers(): void
    {
        Configure::write('Synapse.serverInfo.name', 'Host name');
        Configure::write('Synapse.capabilities.tools', ['App\\CustomTool']);
        Configure::write('Mcp.local.other', 'App\\OtherServer');
        (new SynapsePlugin())->bootstrap($this->createStub(PluginApplicationInterface::class));

        $this->assertSame('Host name', Configure::read('Synapse.serverInfo.name'));
        $this->assertSame(['App\\CustomTool'], Configure::read('Synapse.capabilities.tools'));
        $this->assertSame('5.x', Configure::read('Synapse.prompts.cakephp_version'));
        $this->assertSame(SynapseServer::class, Configure::read('Mcp.local.synapse'));
        $this->assertSame('App\\OtherServer', Configure::read('Mcp.local.other'));
    }

    public function testBootstrapPreservesCustomLocalHandle(): void
    {
        Configure::write('Mcp.local.synapse', 'App\\CustomServer');
        (new SynapsePlugin())->bootstrap($this->createStub(PluginApplicationInterface::class));
        $this->assertSame('App\\CustomServer', Configure::read('Mcp.local.synapse'));
    }

    public function testCommandsAreUpdatedAfterServiceResolutionAndIsolatedBetweenApplications(): void
    {
        $first = new SynapsePlugin();
        $firstContainer = new Container();
        $firstEvents = new EventManager();
        $first->services($firstContainer);
        $first->events($firstEvents);

        $firstService = $firstContainer->get(CommandService::class);
        $this->assertSame(0, $firstService->listCommands()['total']);

        $commands = new CommandCollection();
        $commands->add('test', TestCommand::class);

        $firstEvents->dispatch(new Event('Console.buildCommands', null, ['commands' => $commands]));
        $this->assertSame(1, $firstService->listCommands()['total']);

        $second = new SynapsePlugin();
        $secondContainer = new Container();
        $second->services($secondContainer);
        $this->assertSame(0, $secondContainer->get(CommandService::class)->listCommands()['total']);
    }

    public function testLocalApplicationHasNoMcpHttpHooks(): void
    {
        $app = new Application(CONFIG);
        $app->bootstrap();
        $app->pluginBootstrap();

        $queue = new MiddlewareQueue();
        $this->assertCount(0, $app->pluginMiddleware($queue));
        $routes = Router::createRouteBuilder('/');
        $app->pluginRoutes($routes);
        $this->assertCount(0, Router::routes());
        $commands = $app->pluginConsole(new CommandCollection());
        $this->assertTrue($commands->has('mcp start'));
        $this->assertTrue($commands->has('mcp inspector'));
        $this->assertFalse($commands->has('synapse server'));
    }
}
