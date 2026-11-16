<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\McpPlugin;
use Crustum\Mcp\Support\ContainerRegistry;
use Synapse\SynapsePlugin;

/**
 * Boots the real MCP container bindings without HTTP, OAuth or network access.
 */
abstract class McpTestCase extends TestCase
{
    protected Container $container;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalSynapse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalSynapse = Configure::read('Synapse');
        Configure::load('Synapse.synapse');
        Configure::write('Synapse.documentation.sources', []);
        Configure::write('Synapse.documentation.auto_build', false);
        $this->container = new Container();
        (new McpPlugin())->services($this->container);
        (new SynapsePlugin())->services($this->container);
        ContainerRegistry::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        ContainerRegistry::setInstance(null);
        Configure::delete('Synapse');
        if ($this->originalSynapse !== null) {
            Configure::write('Synapse', $this->originalSynapse);
        }

        parent::tearDown();
    }
}
