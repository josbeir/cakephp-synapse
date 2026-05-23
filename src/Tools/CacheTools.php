<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Cache\Cache;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Cache Tools
 *
 * MCP tools for inspecting and managing CakePHP cache configurations and entries.
 */
class CacheTools
{
    /**
     * List all configured cache configurations.
     *
     * Returns information about each configured cache engine including
     * the engine class name and any configured prefix.
     *
     * @return array<int, array<string, mixed>> List of cache configurations
     */
    #[McpTool(
        name: 'cache_configs',
        description: 'List all configured CakePHP cache configurations with engine information',
    )]
    public function listConfigs(): array
    {
        $configs = Cache::configured();
        $result = [];

        foreach ($configs as $name) {
            $config = Cache::getConfig($name) ?? [];

            $result[] = [
                'name' => $name,
                'className' => $config['className'] ?? $config['engine'] ?? 'Unknown',
                'prefix' => $config['prefix'] ?? null,
                'path' => $config['path'] ?? null,
                'duration' => $config['duration'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Read a value from the cache.
     *
     * Returns the stored value for the given key, or indicates the key was not found.
     *
     * @param string $config Cache configuration name (e.g. 'default')
     * @param string $key Cache key to read
     * @return array{key: string, value: mixed, found: bool} Cache read result
     */
    #[McpTool(
        name: 'cache_read',
        description: 'Read a value from a CakePHP cache configuration by key',
    )]
    public function cacheRead(string $config, string $key): array
    {
        $this->assertConfigured($config);

        $value = Cache::read($key, $config);
        $found = $value !== null;

        return [
            'key' => $key,
            'value' => $found ? $value : null,
            'found' => $found,
        ];
    }

    /**
     * Write a value to the cache.
     *
     * Stores the given string value under the specified key in the cache configuration.
     *
     * @param string $config Cache configuration name (e.g. 'default')
     * @param string $key Cache key to write
     * @param string $value Value to store
     * @return array{key: string, stored: bool} Cache write result
     */
    #[McpTool(
        name: 'cache_write',
        description: 'Write a string value to a CakePHP cache configuration',
    )]
    public function cacheWrite(string $config, string $key, string $value): array
    {
        $this->assertConfigured($config);

        $stored = Cache::write($key, $value, $config);

        return [
            'key' => $key,
            'stored' => $stored,
        ];
    }

    /**
     * Delete a key from the cache.
     *
     * Removes the specified key from the given cache configuration.
     *
     * @param string $config Cache configuration name (e.g. 'default')
     * @param string $key Cache key to delete
     * @return array{key: string, deleted: bool} Cache delete result
     */
    #[McpTool(
        name: 'cache_delete',
        description: 'Delete a key from a CakePHP cache configuration',
    )]
    public function cacheDelete(string $config, string $key): array
    {
        $this->assertConfigured($config);

        $deleted = Cache::delete($key, $config);

        return [
            'key' => $key,
            'deleted' => $deleted,
        ];
    }

    /**
     * Clear all entries from a cache configuration.
     *
     * Removes all cached entries from the specified cache configuration.
     *
     * @param string $config Cache configuration name (e.g. 'default')
     * @return array{config: string, cleared: bool} Cache clear result
     */
    #[McpTool(
        name: 'cache_clear',
        description: 'Clear all entries from a CakePHP cache configuration',
    )]
    public function cacheClear(string $config): array
    {
        $this->assertConfigured($config);

        $cleared = Cache::clear($config);

        return [
            'config' => $config,
            'cleared' => $cleared,
        ];
    }

    /**
     * Assert that a cache configuration exists.
     *
     * @param string $config Cache configuration name to validate
     * @throws \Mcp\Exception\ToolCallException If the configuration is not registered
     */
    private function assertConfigured(string $config): void
    {
        if (!in_array($config, Cache::configured(), true)) {
            throw new ToolCallException(sprintf(
                'Cache config "%s" is not configured. Available: %s',
                $config,
                implode(', ', Cache::configured()),
            ));
        }
    }
}
