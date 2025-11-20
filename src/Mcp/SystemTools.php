<?php
declare(strict_types=1);

namespace Synapse\Mcp;

use Cake\Core\Configure;
use Mcp\Capability\Attribute\McpTool;

/**
 * System Tools
 *
 * Default MCP tools for system information and diagnostics.
 */
class SystemTools
{
    /**
     * Get system information about the CakePHP application.
     *
     * Returns basic information about the application environment,
     * including CakePHP version, PHP version, and debug status.
     *
     * @return array<string, mixed> System information
     */
    #[McpTool(name: 'system_info')]
    public function getSystemInfo(): array
    {
        return [
            'app_name' => Configure::read('App.name', 'CakePHP Application'),
            'cakephp_version' => Configure::version(),
            'php_version' => PHP_VERSION,
            'debug_mode' => Configure::read('debug'),
            'timezone' => Configure::read('App.defaultTimezone', date_default_timezone_get()),
            'encoding' => Configure::read('App.encoding', 'UTF-8'),
        ];
    }

    /**
     * Get current configuration value.
     *
     * Reads a configuration value from the application configuration.
     * Useful for checking application settings.
     *
     * @param string $key Configuration key to read (e.g., 'App.name', 'debug')
     * @return mixed Configuration value or null if not found
     */
    #[McpTool(
        name: 'config_read',
        description: 'Read a specific configuration value from the application',
    )]
    public function readConfig(string $key): mixed
    {
        return Configure::read($key);
    }

    /**
     * Check if application is in debug mode.
     *
     * Returns whether the application is currently running in debug mode.
     *
     * @return array<string, mixed> Debug status information
     */
    #[McpTool(name: 'debug_status')]
    public function getDebugStatus(): array
    {
        return [
            'debug' => Configure::read('debug'),
            'environment' => getenv('APP_ENV') ?: 'production',
        ];
    }
}
