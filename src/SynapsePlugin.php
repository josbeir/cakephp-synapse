<?php
declare(strict_types=1);

namespace Synapse;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManagerInterface;
use Cake\Log\Log;
use Synapse\Command\ServerCommand;
use Synapse\Tools\CommandTools;

/**
 * Synapse Plugin
 *
 * Model Context Protocol (MCP) server plugin for CakePHP.
 * Exposes CakePHP functionality as MCP Tools, Resources, and Prompts.
 */
class SynapsePlugin extends BasePlugin
{
    /**
     * Plugin name
     */
    protected ?string $name = 'Synapse';

    /**
     * Stores the CommandCollection captured from Console.buildCommands event
     */
    protected static ?CommandCollection $commandCollection = null;

    /**
     * Reset the static CommandCollection (for testing purposes)
     */
    public static function resetCommandCollection(): void
    {
        static::$commandCollection = null;
    }

    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * @param \Cake\Core\PluginApplicationInterface<mixed> $app The host application
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        if (!defined('APP')) {
            parent::bootstrap($app);
        }

        Configure::load('Synapse.synapse');

        // Load app specific config file.
        if (file_exists(ROOT . DS . 'config' . DS . 'app_synapse.php')) {
            Configure::load('app_synapse');
        }

        // Configure synapse logger for MCP server (if not already configured)
        $logConfig = Configure::read('Log.synapse');
        if ($logConfig !== null && !Log::getConfig('synapse')) {
            Log::setConfig('synapse', $logConfig);
        }
    }

    /**
     * Register application event listeners.
     *
     * @param \Cake\Event\EventManagerInterface $eventManager The Event Manager to update.
     */
    public function events(EventManagerInterface $eventManager): EventManagerInterface
    {
        // Listen for Console.buildCommands event to capture CommandCollection
        $eventManager->on('Console.buildCommands', function (EventInterface $event): void {
            $commands = $event->getData('commands');

            if ($commands instanceof CommandCollection) {
                // Store in static property so services() can access it
                static::$commandCollection = $commands;
            }
        });

        return $eventManager;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     */
    public function services(ContainerInterface $container): void
    {
        // Register ServerCommand with container for proper DI
        $container->add(ServerCommand::class)
            ->addArgument($container);

        // Register CommandCollection factory that returns the captured collection
        $container->addShared(CommandCollection::class, function (): CommandCollection {
            return static::$commandCollection ?? new CommandCollection();
        });

        // Register CommandTools with CommandCollection dependency
        $container->add(CommandTools::class)
            ->addArgument(CommandCollection::class);
    }
}
