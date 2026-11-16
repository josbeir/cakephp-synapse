<?php
declare(strict_types=1);

namespace Synapse;

use Cake\Console\CommandCollection;
use Cake\Container\ContainerInterface;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManagerInterface;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Server\SynapseServer;
use Synapse\Service\CacheService;
use Synapse\Service\CommandCollectionProvider;
use Synapse\Service\CommandService;
use Synapse\Service\DatabaseService;
use Synapse\Service\DocumentationResourceService;
use Synapse\Service\DocumentationService;
use Synapse\Service\LogService;
use Synapse\Service\ModelService;
use Synapse\Service\RouteService;
use Synapse\Service\SystemService;
use Synapse\Service\TinkerService;
use Synapse\Utility\SubprocessRunner;

/**
 * CakePHP development capabilities for the Crustum MCP local server.
 *
 * Load Crustum/Mcp before this plugin, with HTTP routes and middleware disabled.
 */
class SynapsePlugin extends BasePlugin
{
    public const VERSION = '1.0.0';

    protected ?string $name = 'Synapse';

    private ?CommandCollectionProvider $commands = null;

    /**
     * Load defaults and application overrides, then register the local handle.
     *
     * @param \Cake\Core\PluginApplicationInterface $app Host application
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        $overrides = Configure::read('Synapse', []);
        Configure::load('Synapse.synapse');
        Configure::write('Synapse', array_replace_recursive(Configure::read('Synapse'), $overrides));
        if (file_exists(ROOT . DS . 'config' . DS . 'app_synapse.php')) {
            Configure::load('app_synapse');
        }

        if (!Configure::check('Mcp.local.synapse')) {
            Configure::write('Mcp.local.synapse', SynapseServer::class);
        }
    }

    /**
     * Capture commands in the same provider that is registered with the container.
     *
     * @param \Cake\Event\EventManagerInterface $eventManager Application event manager
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        $provider = $this->commandProvider();
        $eventManager->on('Console.buildCommands', function (EventInterface $event) use ($provider): void {
            $commands = $event->getData('commands');
            if ($commands instanceof CommandCollection) {
                $provider->set($commands);
            }
        });

        return $eventManager;
    }

    /**
     * Register lazy application services used by MCP capabilities.
     *
     * @param \Cake\Container\ContainerInterface $container Application container
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared(CommandCollectionProvider::class, $this->commandProvider());
        $container->addShared(SubprocessRunner::class);
        $container->addShared(DocumentSearchService::class);
        $container->addShared(SystemService::class);
        $container->addShared(DatabaseService::class);
        $container->addShared(RouteService::class);
        $container->addShared(CacheService::class);
        $container->addShared(LogService::class);
        $container->addShared(ModelService::class);
        $container->addShared(CommandService::class)
            ->addArgument(CommandCollectionProvider::class)
            ->addArgument(SubprocessRunner::class);
        $container->addShared(TinkerService::class)->addArgument(SubprocessRunner::class);
        $container->addShared(DocumentationService::class)->addArgument(DocumentSearchService::class);
        $container->addShared(DocumentationResourceService::class)->addArgument(DocumentSearchService::class);
    }

    /**
     * Get the plugin instance's command provider.
     */
    private function commandProvider(): CommandCollectionProvider
    {
        return $this->commands ??= new CommandCollectionProvider();
    }
}
