<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Core\Configure;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * System Tools
 *
 * Default MCP tools for system information and diagnostics.
 */
class SystemTools
{
    /**
     * Configuration key fragments whose values must never cross the MCP boundary.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'secret',
        'token',
        'api_key',
        'apikey',
        'private_key',
        'credential',
        'encryption_key',
    ];

    /**
     * Get system information about the CakePHP application.
     *
     * Returns basic information about the application environment,
     * including CakePHP version, PHP version, and debug status.
     *
     * @return array<string, mixed> System information
     */
    #[McpTool(name: 'system_info', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
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
        description: 'Read a specific application configuration value; sensitive keys are redacted',
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true),
    )]
    public function readConfig(string $key): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        return $this->redactConfigValue(Configure::read($key));
    }

    /**
     * Check if application is in debug mode.
     *
     * Returns whether the application is currently running in debug mode.
     *
     * @return array<string, mixed> Debug status information
     */
    #[McpTool(name: 'debug_status', annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true))]
    public function getDebugStatus(): array
    {
        $result = [
            'debug' => Configure::read('debug'),
        ];

        $env = getenv('APP_ENV');
        if ($env !== false) {
            $result['environment'] = $env;
        }

        return $result;
    }

    /**
     * List environment variable names with values redacted.
     *
     * Returns environment variable names without exposing credentials or other
     * sensitive values to the MCP client.
     *
     * @return array<string, string> Environment variable names and redacted values
     */
    #[McpTool(
        name: 'list_env_vars',
        description: 'List available environment variable names with all values redacted',
        annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true),
    )]
    public function listEnvVars(): array
    {
        $variables = getenv();
        if (!is_array($variables)) {
            return [];
        }

        return array_fill_keys(array_keys($variables), '[REDACTED]');
    }

    /**
     * Redact sensitive values in a configuration tree while preserving safe metadata.
     *
     * @param mixed $value Configuration value
     * @return mixed Configuration value with sensitive entries redacted
     */
    private function redactConfigValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $entry) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = $this->redactConfigValue($entry);
        }

        return $redacted;
    }

    /**
     * Check whether a configuration path or array key is sensitive.
     */
    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '.'], '_', $key));

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
