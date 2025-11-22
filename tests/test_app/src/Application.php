<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Synapse\SynapsePlugin;
use Synapse\TestSuite\MockGitAdapter;

/**
 * @extends BaseApplication<mixed>
 */
class Application extends BaseApplication
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    public function bootstrap(): void
    {
        if (!defined('APP')) {
            parent::bootstrap();
        }

        $this->addPlugin(SynapsePlugin::class);
    }

    public function pluginBootstrap(): void
    {
        parent::pluginBootstrap();

        // Allows MCP tool discovery from test_app
        Configure::write('Synapse.discovery.scanDirs', ['../../src']);

        // Make sure we override he Mock adapter in our application context too.
        Configure::write('Synapse.documentation.git_adapter', MockGitAdapter::class);

        // Override sources to empty array to prevent real repos from being used during tests
        Configure::write('Synapse.documentation.sources', []);
    }

    public function routes(RouteBuilder $routes): void
    {
    }
}
