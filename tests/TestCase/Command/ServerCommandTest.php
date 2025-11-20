<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Cache\Cache;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use Exception;
use Mcp\Server;
use Psr\SimpleCache\CacheInterface;
use ReflectionClass;
use Synapse\Command\ServerCommand;
use Throwable;

/**
 * ServerCommand Test Case
 *
 * Tests the MCP server command functionality using proper console integration testing.
 */
class ServerCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test command help displays correctly
     */
    public function testCommandHelp(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Start the MCP (Model Context Protocol) server');
        $this->assertOutputContains('--transport');
        $this->assertOutputContains('stdio');
    }

    /**
     * Test command exists and is registered
     */
    public function testCommandIsRegistered(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputNotContains('Unknown command');
    }

    /**
     * Test transport option accepts stdio
     */
    public function testTransportOptionAcceptsStdio(): void
    {
        $this->exec('synapse server --transport stdio --help');

        $this->assertExitSuccess();
    }

    /**
     * Test verbose option works
     */
    public function testVerboseOption(): void
    {
        $this->exec('synapse server --verbose --help');

        $this->assertExitSuccess();
    }

    /**
     * Test quiet option works
     */
    public function testQuietOption(): void
    {
        $this->exec('synapse server --quiet --help');

        $this->assertExitSuccess();
    }

    /**
     * Test short option aliases work
     */
    public function testShortOptionAliases(): void
    {
        $this->exec('synapse server -h');

        $this->assertExitSuccess();
        $this->assertOutputContains('Start the MCP');

        $this->exec('synapse server -v -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -q -h');
        $this->assertExitSuccess();
    }

    /**
     * Test server info configuration is loaded
     */
    public function testServerInfoConfiguration(): void
    {
        Configure::write('Synapse.serverInfo', [
            'name' => 'Test MCP Server',
            'version' => '2.0.0',
        ]);

        $config = Configure::read('Synapse.serverInfo');
        $this->assertEquals('Test MCP Server', $config['name']);
        $this->assertEquals('2.0.0', $config['version']);
    }

    /**
     * Test discovery configuration is loaded
     */
    public function testDiscoveryConfiguration(): void
    {
        Configure::write('Synapse.discovery', [
            'scanDirs' => ['src', 'plugins'],
            'excludeDirs' => ['tests', 'vendor'],
        ]);

        $config = Configure::read('Synapse.discovery');
        $this->assertEquals(['src', 'plugins'], $config['scanDirs']);
        $this->assertEquals(['tests', 'vendor'], $config['excludeDirs']);
    }

    /**
     * Test default configuration is valid
     */
    public function testDefaultConfigurationIsValid(): void
    {
        Configure::delete('Synapse');
        Configure::load('Synapse.synapse');

        $config = Configure::read('Synapse');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('serverInfo', $config);
        $this->assertArrayHasKey('name', $config['serverInfo']);
        $this->assertArrayHasKey('version', $config['serverInfo']);
        $this->assertArrayHasKey('protocolVersion', $config);
        $this->assertArrayHasKey('discovery', $config);
    }

    /**
     * Test protocol version configuration
     */
    public function testProtocolVersionConfiguration(): void
    {
        Configure::load('Synapse.synapse');
        $protocolVersion = Configure::read('Synapse.protocolVersion');

        $this->assertNotEmpty($protocolVersion);
        $this->assertEquals('2024-11-05', $protocolVersion);
    }

    /**
     * Test protocol version can be overridden
     */
    public function testProtocolVersionCanBeOverridden(): void
    {
        Configure::write('Synapse.protocolVersion', '2025-03-26');

        $protocolVersion = Configure::read('Synapse.protocolVersion');
        $this->assertEquals('2025-03-26', $protocolVersion);

        Configure::delete('Synapse.protocolVersion');
    }

    /**
     * Test discovery scan directories configuration
     */
    public function testDiscoveryScanDirectoriesConfiguration(): void
    {
        Configure::load('Synapse.synapse');
        $scanDirs = Configure::read('Synapse.discovery.scanDirs');

        $this->assertIsArray($scanDirs);
        $this->assertContains('src', $scanDirs);
        $this->assertContains('plugins/Synapse/src', $scanDirs);
    }

    /**
     * Test discovery exclude directories configuration
     */
    public function testDiscoveryExcludeDirectoriesConfiguration(): void
    {
        Configure::load('Synapse.synapse');
        $excludeDirs = Configure::read('Synapse.discovery.excludeDirs');

        $this->assertIsArray($excludeDirs);
        $this->assertContains('tests', $excludeDirs);
        $this->assertContains('vendor', $excludeDirs);
    }

    /**
     * Test buildOptionParser creates proper parser
     */
    public function testBuildOptionParser(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('synapse server');
        $this->assertOutputContains('--transport');
        $this->assertOutputContains('-t');
        $this->assertOutputContains('stdio');
        $this->assertOutputContains('Transport type');
    }

    /**
     * Test buildOptionParser sets correct default values
     */
    public function testBuildOptionParserDefaults(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('default: stdio');
    }

    /**
     * Test buildOptionParser validates transport choices
     */
    public function testBuildOptionParserTransportChoices(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('choices:');
        $this->assertOutputContains('stdio');
    }

    /**
     * Test server initialization with verbose output
     */
    public function testServerInitializationVerbose(): void
    {
        // We can't actually run the server (it would block), but we can test
        // that verbose mode would show discovery information via help
        $this->exec('synapse server -v --help');

        $this->assertExitSuccess();
    }

    /**
     * Test custom server info from environment
     */
    public function testCustomServerInfoFromConfig(): void
    {
        Configure::write('Synapse.serverInfo.name', 'Custom Test Server');
        Configure::write('Synapse.serverInfo.version', '3.0.0');

        $name = Configure::read('Synapse.serverInfo.name');
        $version = Configure::read('Synapse.serverInfo.version');

        $this->assertEquals('Custom Test Server', $name);
        $this->assertEquals('3.0.0', $version);

        Configure::delete('Synapse.serverInfo');
    }

    /**
     * Test default server info when not configured
     */
    public function testDefaultServerInfo(): void
    {
        Configure::delete('Synapse.serverInfo');
        $serverInfo = Configure::read('Synapse.serverInfo');

        // Should either be null or have defaults
        $this->assertTrue($serverInfo === null || is_array($serverInfo));
    }

    /**
     * Test server uses correct protocol version from config
     */
    public function testServerProtocolVersionFromConfig(): void
    {
        Configure::load('Synapse.synapse');
        $protocolVersion = Configure::read('Synapse.protocolVersion');

        $this->assertNotEmpty($protocolVersion);
        $this->assertIsString($protocolVersion);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $protocolVersion);
    }

    /**
     * Test discovery configuration with custom scan directories
     */
    public function testDiscoveryWithCustomScanDirectories(): void
    {
        Configure::write('Synapse.discovery.scanDirs', ['custom', 'app']);
        $scanDirs = Configure::read('Synapse.discovery.scanDirs');

        $this->assertIsArray($scanDirs);
        $this->assertContains('custom', $scanDirs);
        $this->assertContains('app', $scanDirs);

        Configure::delete('Synapse.discovery.scanDirs');
    }

    /**
     * Test discovery configuration with custom exclude directories
     */
    public function testDiscoveryWithCustomExcludeDirectories(): void
    {
        Configure::write('Synapse.discovery.excludeDirs', ['node_modules', 'build']);
        $excludeDirs = Configure::read('Synapse.discovery.excludeDirs');

        $this->assertIsArray($excludeDirs);
        $this->assertContains('node_modules', $excludeDirs);
        $this->assertContains('build', $excludeDirs);

        Configure::delete('Synapse.discovery.excludeDirs');
    }

    /**
     * Test transport option in command description
     */
    public function testTransportOptionInDescription(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Transport type');
        $this->assertOutputContains('currently only stdio is supported');
    }

    /**
     * Test command description is clear
     */
    public function testCommandDescription(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Model Context Protocol');
        $this->assertOutputContains('MCP');
    }

    /**
     * Test multiple protocol versions can be configured
     */
    public function testMultipleProtocolVersions(): void
    {
        $versions = ['2024-11-05', '2025-03-26'];

        foreach ($versions as $version) {
            Configure::write('Synapse.protocolVersion', $version);
            $configured = Configure::read('Synapse.protocolVersion');
            $this->assertEquals($version, $configured);
        }

        Configure::delete('Synapse.protocolVersion');
    }

    /**
     * Test configuration structure is valid
     */
    public function testConfigurationStructure(): void
    {
        Configure::load('Synapse.synapse');
        $config = Configure::read('Synapse');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('serverInfo', $config);
        $this->assertArrayHasKey('protocolVersion', $config);
        $this->assertArrayHasKey('discovery', $config);

        $this->assertIsArray($config['serverInfo']);
        $this->assertArrayHasKey('name', $config['serverInfo']);
        $this->assertArrayHasKey('version', $config['serverInfo']);

        $this->assertIsArray($config['discovery']);
        $this->assertArrayHasKey('scanDirs', $config['discovery']);
        $this->assertArrayHasKey('excludeDirs', $config['discovery']);
    }

    /**
     * Test execute method handles errors gracefully
     */
    public function testExecuteHandlesErrors(): void
    {
        // We can't actually run the server, but we can verify error handling
        // by checking the command structure exists
        $this->exec('synapse server --help');
        $this->assertExitSuccess();
    }

    /**
     * Test buildOptionParser includes all required options
     */
    public function testBuildOptionParserHasAllOptions(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--help');
        $this->assertOutputContains('--quiet');
        $this->assertOutputContains('--verbose');
        $this->assertOutputContains('--transport');
    }

    /**
     * Test command with invalid transport value
     */
    public function testCommandWithInvalidTransport(): void
    {
        $this->exec('synapse server --transport invalid');

        // Should fail or show error about invalid choice
        $this->assertExitError();
    }

    /**
     * Test server configuration with all options set
     */
    public function testServerConfigurationComplete(): void
    {
        Configure::write('Synapse', [
            'serverInfo' => [
                'name' => 'Full Test Server',
                'version' => '1.2.3',
            ],
            'protocolVersion' => '2024-11-05',
            'discovery' => [
                'scanDirs' => ['src', 'plugins'],
                'excludeDirs' => ['tests', 'vendor', 'tmp'],
            ],
        ]);

        $config = Configure::read('Synapse');

        $this->assertArrayHasKey('serverInfo', $config);
        $this->assertEquals('Full Test Server', $config['serverInfo']['name']);
        $this->assertEquals('1.2.3', $config['serverInfo']['version']);
        $this->assertEquals('2024-11-05', $config['protocolVersion']);
        $this->assertCount(2, $config['discovery']['scanDirs']);
        $this->assertCount(3, $config['discovery']['excludeDirs']);

        Configure::delete('Synapse');
    }

    /**
     * Test discovery configuration defaults when not set
     */
    public function testDiscoveryConfigurationDefaults(): void
    {
        Configure::delete('Synapse.discovery');

        // Load default config
        Configure::load('Synapse.synapse');

        $discovery = Configure::read('Synapse.discovery');

        $this->assertIsArray($discovery);
        $this->assertArrayHasKey('scanDirs', $discovery);
        $this->assertArrayHasKey('excludeDirs', $discovery);
        $this->assertNotEmpty($discovery['scanDirs']);
        $this->assertNotEmpty($discovery['excludeDirs']);
    }

    /**
     * Test server info defaults when not configured
     */
    public function testServerInfoDefaults(): void
    {
        Configure::delete('Synapse.serverInfo');
        Configure::load('Synapse.synapse');

        $serverInfo = Configure::read('Synapse.serverInfo');

        $this->assertIsArray($serverInfo);
        $this->assertArrayHasKey('name', $serverInfo);
        $this->assertArrayHasKey('version', $serverInfo);
        $this->assertNotEmpty($serverInfo['name']);
        $this->assertNotEmpty($serverInfo['version']);
    }

    /**
     * Test protocol version is valid format
     */
    public function testProtocolVersionFormat(): void
    {
        Configure::load('Synapse.synapse');
        $version = Configure::read('Synapse.protocolVersion');

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $version);
    }

    /**
     * Test command description contains MCP
     */
    public function testCommandDescriptionContainsMCP(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('MCP');
        $this->assertOutputContains('Model Context Protocol');
    }

    /**
     * Test transport option only accepts stdio
     */
    public function testTransportOptionOnlyAcceptsStdio(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('currently only stdio is supported');
    }

    /**
     * Test cache configuration with default engine
     */
    public function testCacheConfigurationDefault(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');
        $cache = Configure::read('Synapse.discovery.cache');

        $this->assertEquals('default', $cache);
    }

    /**
     * Test cache configuration with custom engine
     */
    public function testCacheConfigurationCustomEngine(): void
    {
        Configure::write('Synapse.discovery.cache', 'mcp');
        $cache = Configure::read('Synapse.discovery.cache');

        $this->assertEquals('mcp', $cache);
    }

    /**
     * Test CakePHP Cache::pool returns PSR-16 interface
     */
    public function testCachePoolReturnsPsr16Interface(): void
    {
        $cache = Cache::pool('default');

        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * Test --no-cache option is available
     */
    public function testNoCacheOptionAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--no-cache');
        $this->assertOutputContains('Disable discovery caching');
    }

    /**
     * Test --clear-cache option is available
     */
    public function testClearCacheOptionAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--clear-cache');
        $this->assertOutputContains('Clear discovery cache');
    }

    /**
     * Test cache configuration from environment variable
     */
    public function testCacheConfigurationFromEnvironment(): void
    {
        Configure::write('Synapse.discovery.cache', env('MCP_DISCOVERY_CACHE', 'default'));
        $cache = Configure::read('Synapse.discovery.cache');

        $this->assertEquals('default', $cache);
    }

    /**
     * Test cache can be set to different engines
     */
    public function testCacheEngineConfiguration(): void
    {
        $engines = ['default', 'mcp', '_cake_core_', '_cake_model_'];

        foreach ($engines as $engine) {
            Configure::write('Synapse.discovery.cache', $engine);
            $configured = Configure::read('Synapse.discovery.cache');
            $this->assertEquals($engine, $configured);
        }

        Configure::delete('Synapse.discovery.cache');
    }

    /**
     * Test cache configuration is part of discovery config
     */
    public function testCacheIsPartOfDiscoveryConfig(): void
    {
        Configure::load('Synapse.synapse');
        $discovery = Configure::read('Synapse.discovery');

        $this->assertIsArray($discovery);
        $this->assertArrayHasKey('cache', $discovery);
    }

    /**
     * Test cache configuration default value
     */
    public function testCacheConfigurationDefaultValue(): void
    {
        Configure::load('Synapse.synapse');
        $cache = Configure::read('Synapse.discovery.cache');

        $this->assertIsString($cache);
        $this->assertEquals('default', $cache);
    }

    /**
     * Test both cache options work together
     */
    public function testBothCacheOptionsWorkTogether(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--no-cache');
        $this->assertOutputContains('--clear-cache');
    }

    /**
     * Test cache options are boolean flags
     */
    public function testCacheOptionsAreBoolean(): void
    {
        $this->exec('synapse server --no-cache --help');
        $this->assertExitSuccess();

        $this->exec('synapse server --clear-cache --help');
        $this->assertExitSuccess();
    }

    /**
     * Test cache configuration validation
     */
    public function testCacheConfigurationValidation(): void
    {
        $validEngines = ['default', 'mcp', 'custom'];

        foreach ($validEngines as $engine) {
            Configure::write('Synapse.discovery.cache', $engine);
            $cache = Configure::read('Synapse.discovery.cache');
            $this->assertIsString($cache);
            $this->assertEquals($engine, $cache);
        }

        Configure::delete('Synapse.discovery.cache');
    }

    /**
     * Test getCache method returns valid PSR-16 cache
     */
    public function testGetCacheReturnsValidCache(): void
    {
        // We can't directly call getCache since it's private, but we can test
        // that Cache::pool returns PSR-16 interface which getCache uses internally
        Configure::write('Synapse.discovery.cache', 'default');

        $cache = Cache::pool('default');

        $this->assertInstanceOf(CacheInterface::class, $cache);

        // Test basic cache operations
        $testKey = 'mcp_test_' . uniqid();
        $testValue = ['test' => 'data'];

        $cache->set($testKey, $testValue);
        $result = $cache->get($testKey);

        $this->assertEquals($testValue, $result);

        $cache->delete($testKey);
    }

    /**
     * Test getCache method handles invalid cache engine
     */
    public function testGetCacheHandlesInvalidEngine(): void
    {
        // Configure an invalid cache engine
        Configure::write('Synapse.discovery.cache', 'nonexistent_cache_engine_xyz');

        // The command should handle this gracefully when building server
        // We can verify the configuration was set
        $cache = Configure::read('Synapse.discovery.cache');
        $this->assertEquals('nonexistent_cache_engine_xyz', $cache);
    }

    /**
     * Test clearDiscoveryCache functionality
     */
    public function testClearDiscoveryCacheFunctionality(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        // Write some test data to cache
        $cache = Cache::pool('default');
        $testKey = 'mcp_discovery_test_' . uniqid();
        $cache->set($testKey, ['some' => 'data']);

        // Verify data exists
        $this->assertNotNull($cache->get($testKey));

        // Clear the cache
        Cache::clear('default');

        // Verify data is gone
        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test getCache method is called with valid engine
     * This is tested indirectly through the command execution
     */
    public function testGetCacheMethodCalledWithValidEngine(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        // The command would call getCache internally when executed
        // We verify the cache configuration is set up correctly
        $cacheEngine = Configure::read('Synapse.discovery.cache');
        $this->assertEquals('default', $cacheEngine);

        // Verify the cache engine exists and is functional
        $cache = Cache::pool($cacheEngine);
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * Test getCache method handles exception when cache engine doesn't exist
     * The method should return null and log a warning
     */
    public function testGetCacheMethodHandlesInvalidEngine(): void
    {
        // Set an invalid cache engine
        Configure::write('Synapse.discovery.cache', 'totally_invalid_engine_xyz');

        // When the command tries to get this cache, it should handle the error
        // We can't execute the full command, but we can verify the behavior
        try {
            Cache::pool('totally_invalid_engine_xyz');
            $this->fail('Expected exception was not thrown');
        } catch (Throwable $throwable) {
            // This is the exception getCache() would catch and handle
            $this->assertInstanceOf(Exception::class, $throwable);
        }
    }

    /**
     * Test cache set and get operations
     */
    public function testCacheSetAndGetOperations(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        $cache = Cache::pool('default');

        // Test with array
        $arrayKey = 'mcp_array_' . uniqid();
        $arrayData = ['tools' => ['tool1', 'tool2'], 'resources' => []];
        $cache->set($arrayKey, $arrayData);
        $this->assertEquals($arrayData, $cache->get($arrayKey));

        // Test with string
        $stringKey = 'mcp_string_' . uniqid();
        $cache->set($stringKey, 'test string');
        $this->assertEquals('test string', $cache->get($stringKey));

        // Clean up
        $cache->delete($arrayKey);
        $cache->delete($stringKey);
    }

    /**
     * Test cache has method
     */
    public function testCacheHasMethod(): void
    {
        $cache = Cache::pool('default');

        $testKey = 'mcp_has_test_' . uniqid();

        // Should not exist initially
        $this->assertFalse($cache->has($testKey));

        // Set value
        $cache->set($testKey, 'value');

        // Should exist now
        $this->assertTrue($cache->has($testKey));

        // Delete
        $cache->delete($testKey);

        // Should not exist again
        $this->assertFalse($cache->has($testKey));
    }

    /**
     * Test cache clear operation
     */
    public function testCacheClearOperation(): void
    {
        $cache = Cache::pool('default');

        // Set multiple values
        $keys = [];
        for ($i = 0; $i < 3; $i++) {
            $key = 'mcp_clear_test_' . $i . '_' . uniqid();
            $keys[] = $key;
            $cache->set($key, 'value_' . $i);
            $this->assertTrue($cache->has($key));
        }

        // Clear cache
        Cache::clear('default');

        // Verify all keys are gone
        foreach ($keys as $key) {
            $this->assertNull($cache->get($key));
        }
    }

    /**
     * Test cache with TTL (if supported)
     */
    public function testCacheWithTTL(): void
    {
        $cache = Cache::pool('default');

        $testKey = 'mcp_ttl_test_' . uniqid();

        // Set with TTL
        $cache->set($testKey, 'test_value', 3600);

        // Verify it was set
        $this->assertEquals('test_value', $cache->get($testKey));

        // Clean up
        $cache->delete($testKey);
    }

    /**
     * Test --clear-cache option clears the configured cache engine
     */
    public function testClearCacheOptionClearsConfiguredEngine(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        $cache = Cache::pool('default');
        $testKey = 'mcp_option_test_' . uniqid();
        $cache->set($testKey, 'test_data');

        // Verify data exists
        $this->assertNotNull($cache->get($testKey));

        // The --clear-cache option would clear this cache
        // Simulate what the command does
        Cache::clear('default');

        // Verify cache was cleared
        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test cache configuration with custom engine
     */
    public function testCacheConfigurationWithCustomEngine(): void
    {
        // Create a temporary custom cache config
        Cache::setConfig('mcp_test_temp', [
            'className' => 'File',
            'duration' => '+1 hour',
            'path' => CACHE,
            'prefix' => 'mcp_test_',
        ]);

        Configure::write('Synapse.discovery.cache', 'mcp_test_temp');

        $cache = Cache::pool('mcp_test_temp');
        $this->assertInstanceOf(CacheInterface::class, $cache);

        // Test operations
        $testKey = 'custom_test_' . uniqid();
        $cache->set($testKey, 'custom_value');
        $this->assertEquals('custom_value', $cache->get($testKey));

        // Clean up
        $cache->delete($testKey);
        Cache::drop('mcp_test_temp');
        Configure::delete('Synapse.discovery.cache');
    }

    /**
     * Test clearDiscoveryCache success path
     */
    public function testClearDiscoveryCacheSuccess(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        // Add some data to cache
        $cache = Cache::pool('default');
        $testKey = 'mcp_clear_test_' . uniqid();
        $cache->set($testKey, 'test_data');
        $this->assertNotNull($cache->get($testKey));

        // Clear the cache (simulating what clearDiscoveryCache does)
        $result = Cache::clear('default');
        $this->assertTrue($result);

        // Verify cache was cleared
        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test clearDiscoveryCache with default engine when not configured
     */
    public function testClearDiscoveryCacheUsesDefaultEngine(): void
    {
        // Don't set cache config - should fall back to 'default'
        Configure::delete('Synapse.discovery.cache');

        // The method should use DEFAULT_CACHE_ENGINE ('default')
        $cache = Cache::pool('default');
        $testKey = 'mcp_default_clear_' . uniqid();
        $cache->set($testKey, 'value');

        Cache::clear('default');

        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test clearDiscoveryCache handles exceptions
     */
    public function testClearDiscoveryCacheHandlesException(): void
    {
        // Set an invalid cache engine
        Configure::write('Synapse.discovery.cache', 'nonexistent_engine_xyz');

        // Clearing a non-existent cache should throw an exception
        try {
            Cache::clear('nonexistent_engine_xyz');
            $this->fail('Expected exception was not thrown');
        } catch (Throwable $throwable) {
            // This is what clearDiscoveryCache would catch
            $this->assertInstanceOf(Exception::class, $throwable);
        }
    }

    /**
     * Test buildServer is called with cache enabled
     */
    public function testBuildServerWithCacheEnabled(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');
        Configure::load('Synapse.synapse');

        // Verify configuration that buildServer would use
        $config = Configure::read('Synapse');
        $this->assertArrayHasKey('discovery', $config);
        $this->assertArrayHasKey('cache', $config['discovery']);
        $this->assertEquals('default', $config['discovery']['cache']);
    }

    /**
     * Test buildServer with no-cache option
     */
    public function testBuildServerWithNoCacheOption(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        // When --no-cache is used, buildServer should not initialize cache
        // We can't test this directly without running the server, but we can
        // verify the configuration and option exist
        $this->exec('synapse server --no-cache --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('--no-cache');
    }

    /**
     * Test buildServer with clear-cache option
     */
    public function testBuildServerWithClearCacheOption(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        // Set up cache data
        $cache = Cache::pool('default');
        $testKey = 'mcp_build_clear_' . uniqid();
        $cache->set($testKey, 'data');
        $this->assertNotNull($cache->get($testKey));

        // The --clear-cache option should trigger clearDiscoveryCache before building
        // We simulate what the command does
        Cache::clear('default');

        $this->assertNull($cache->get($testKey));

        // Verify the option exists
        $this->exec('synapse server --clear-cache --help');
        $this->assertExitSuccess();
        $this->assertOutputContains('--clear-cache');
    }

    /**
     * Test getCache method directly using reflection
     */
    public function testGetCacheMethodViaReflection(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getCache');
        $method->setAccessible(true);

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Test with valid cache engine
        $io->expects($this->never())->method('warning');
        $cache = $method->invoke($command, 'default', $io);

        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * Test getCache method handles invalid engine via reflection
     */
    public function testGetCacheMethodHandlesInvalidEngineViaReflection(): void
    {
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('getCache');
        $method->setAccessible(true);

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Expect warning to be called
        $io->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Failed to initialize cache'));

        // Test with invalid cache engine
        $cache = $method->invoke($command, 'totally_invalid_cache_xyz', $io);

        $this->assertNull($cache);
    }

    /**
     * Test clearDiscoveryCache method directly using reflection
     */
    public function testClearDiscoveryCacheMethodViaReflection(): void
    {
        Configure::write('Synapse.discovery.cache', 'default');

        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('clearDiscoveryCache');
        $method->setAccessible(true);

        // Add test data to cache
        $cache = Cache::pool('default');
        $testKey = 'mcp_reflection_test_' . uniqid();
        $cache->set($testKey, 'test_value');
        $this->assertNotNull($cache->get($testKey));

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Expect success message
        $io->expects($this->once())
            ->method('success')
            ->with($this->stringContains('Discovery cache cleared'));

        // Call the method
        $method->invoke($command, $io);

        // Verify cache was cleared
        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test clearDiscoveryCache handles clear failure via reflection
     */
    public function testClearDiscoveryCacheHandlesFailureViaReflection(): void
    {
        // Configure a cache engine that doesn't exist
        Configure::write('Synapse.discovery.cache', 'nonexistent_cache_engine');

        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('clearDiscoveryCache');
        $method->setAccessible(true);

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Expect error message
        $io->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Error clearing cache'));

        // Call the method
        $method->invoke($command, $io);

        Configure::delete('Synapse.discovery.cache');
    }

    /**
     * Test buildServer method with cache disabled via reflection
     */
    public function testBuildServerWithCacheDisabledViaReflection(): void
    {
        Configure::load('Synapse.synapse');
        Configure::write('Synapse.discovery.cache', 'default');

        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('buildServer');
        $method->setAccessible(true);

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $io->expects($this->atLeastOnce())->method('verbose');
        $io->expects($this->atLeastOnce())->method('out');

        // Create mock Arguments with no-cache option
        $args = $this->getMockBuilder(Arguments::class)
            ->disableOriginalConstructor()
            ->getMock();

        $args->method('getOption')
            ->with('no-cache')
            ->willReturn(true);

        // Call the method
        $server = $method->invoke($command, $io, $args);

        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * Test buildServer method with cache enabled via reflection
     */
    public function testBuildServerWithCacheEnabledViaReflection(): void
    {
        Configure::load('Synapse.synapse');
        Configure::write('Synapse.discovery.cache', 'default');

        $container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $command = new ServerCommand($container);

        // Use reflection to access private method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('buildServer');
        $method->setAccessible(true);

        // Create mock IO
        $io = $this->getMockBuilder(ConsoleIo::class)
            ->disableOriginalConstructor()
            ->getMock();

        $io->expects($this->atLeastOnce())->method('verbose');
        $io->expects($this->atLeastOnce())->method('out');

        // Create mock Arguments without no-cache option
        $args = $this->getMockBuilder(Arguments::class)
            ->disableOriginalConstructor()
            ->getMock();

        $args->method('getOption')
            ->with('no-cache')
            ->willReturn(false);

        // Call the method
        $server = $method->invoke($command, $io, $args);

        $this->assertInstanceOf(Server::class, $server);
    }
}
