<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\Tools\CacheTools;

/**
 * CacheTools Test Case
 *
 * Tests for cache inspection and manipulation MCP tools.
 * Uses the 'default' File cache engine configured in bootstrap.php.
 */
class CacheToolsTest extends TestCase
{
    private CacheTools $cacheTools;

    /**
     * @var array<array{config: string, key: string}> cache entries written during tests
     */
    private array $writtenEntries = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheTools = new CacheTools();
    }

    protected function tearDown(): void
    {
        foreach ($this->writtenEntries as ['config' => $config, 'key' => $key]) {
            Cache::delete($key, $config);
        }

        $this->writtenEntries = [];

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeCache(string $key, mixed $value, string $config = 'default'): void
    {
        Cache::write($key, $value, $config);
        $this->writtenEntries[] = ['config' => $config, 'key' => $key];
    }

    // -------------------------------------------------------------------------
    // cache_configs
    // -------------------------------------------------------------------------

    /**
     * Test cache_configs returns an array.
     */
    public function testListConfigsReturnsArray(): void
    {
        $result = $this->cacheTools->listConfigs();

        $this->assertGreaterThan(0, count($result));
    }

    /**
     * Test cache_configs includes the 'default' entry.
     */
    public function testListConfigsContainsDefault(): void
    {
        $result = $this->cacheTools->listConfigs();
        $names = array_column($result, 'name');

        $this->assertContains('default', $names);
    }

    /**
     * Test each entry in cache_configs has the expected structure.
     */
    public function testListConfigsEntryStructure(): void
    {
        $result = $this->cacheTools->listConfigs();
        $entry = $result[0];

        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('className', $entry);
        $this->assertIsString($entry['name']);
        $this->assertIsString($entry['className']);
    }

    // -------------------------------------------------------------------------
    // cache_read
    // -------------------------------------------------------------------------

    /**
     * Test cache_read returns found=false and value=null for a missing key.
     */
    public function testCacheReadMissingKey(): void
    {
        $result = $this->cacheTools->cacheRead('default', 'synapse_test_missing_' . uniqid());

        $this->assertSame(false, $result['found']);
        $this->assertNull($result['value']);
    }

    /**
     * Test cache_read returns found=true and the stored value.
     */
    public function testCacheReadFoundKey(): void
    {
        $key = 'synapse_test_read_' . uniqid();
        $this->writeCache($key, 'hello_world');

        $result = $this->cacheTools->cacheRead('default', $key);

        $this->assertSame(true, $result['found']);
        $this->assertSame('hello_world', $result['value']);
        $this->assertSame($key, $result['key']);
    }

    /**
     * Test cache_read throws ToolCallException for an unknown config.
     */
    public function testCacheReadInvalidConfigThrows(): void
    {
        $this->expectException(ToolCallException::class);

        $this->cacheTools->cacheRead('nonexistent_config_xyz', 'any_key');
    }

    // -------------------------------------------------------------------------
    // cache_write
    // -------------------------------------------------------------------------

    /**
     * Test cache_write stores a value that can be read back.
     */
    public function testCacheWriteStoresValue(): void
    {
        $key = 'synapse_test_write_' . uniqid();
        $this->writtenEntries[] = ['config' => 'default', 'key' => $key];

        $result = $this->cacheTools->cacheWrite('default', $key, 'stored_value');

        $this->assertSame(true, $result['stored']);
        $this->assertSame($key, $result['key']);
        $this->assertSame('stored_value', Cache::read($key, 'default'));
    }

    /**
     * Test cache_write throws ToolCallException for an unknown config.
     */
    public function testCacheWriteInvalidConfigThrows(): void
    {
        $this->expectException(ToolCallException::class);

        $this->cacheTools->cacheWrite('nonexistent_config_xyz', 'key', 'value');
    }

    // -------------------------------------------------------------------------
    // cache_delete
    // -------------------------------------------------------------------------

    /**
     * Test cache_delete removes a previously stored key.
     */
    public function testCacheDeleteRemovesKey(): void
    {
        $key = 'synapse_test_delete_' . uniqid();
        $this->writeCache($key, 'to_be_deleted');

        $result = $this->cacheTools->cacheDelete('default', $key);

        $this->assertSame(true, $result['deleted']);
        $this->assertSame($key, $result['key']);
        $this->assertNull(Cache::read($key, 'default'));
    }

    /**
     * Test cache_delete throws ToolCallException for an unknown config.
     */
    public function testCacheDeleteInvalidConfigThrows(): void
    {
        $this->expectException(ToolCallException::class);

        $this->cacheTools->cacheDelete('nonexistent_config_xyz', 'key');
    }

    // -------------------------------------------------------------------------
    // cache_clear
    // -------------------------------------------------------------------------

    /**
     * Test cache_clear removes all entries from the specified config.
     */
    public function testCacheClearClearsConfig(): void
    {
        // Write two keys — don't track them for tearDown since clear removes them
        $key1 = 'synapse_test_clear_a_' . uniqid();
        $key2 = 'synapse_test_clear_b_' . uniqid();
        Cache::write($key1, 'value1', 'default');
        Cache::write($key2, 'value2', 'default');

        $result = $this->cacheTools->cacheClear('default');

        $this->assertSame(true, $result['cleared']);
        $this->assertSame('default', $result['config']);
        $this->assertNull(Cache::read($key1, 'default'));
        $this->assertNull(Cache::read($key2, 'default'));
    }

    /**
     * Test cache_clear throws ToolCallException for an unknown config.
     */
    public function testCacheClearInvalidConfigThrows(): void
    {
        $this->expectException(ToolCallException::class);

        $this->cacheTools->cacheClear('nonexistent_config_xyz');
    }
}
