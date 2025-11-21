<?php
declare(strict_types=1);

namespace Synapse;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Log\Log;
use Synapse\Command\ServerCommand;

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
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     */
    public function services(ContainerInterface $container): void
    {
        // Register ServerCommand with container for proper DI
        $container->add(ServerCommand::class)
            ->addArgument($container);
    }
}
