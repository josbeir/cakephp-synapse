<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Builder;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\TestSuite\TestCase;
use Mcp\Server;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Synapse\Builder\ServerBuilder;
use Synapse\SynapsePlugin;

/**
 * ServerBuilder Test Case
 *
 * Tests the ServerBuilder class for building and configuring MCP servers.
 */
class ServerBuilderTest extends TestCase
{
    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        Configure::load('Synapse.synapse');
    }

    /**
     * Test default configuration
     */
    public function testDefaultConfiguration(): void
    {
        $builder = new ServerBuilder();

        $this->assertEquals(['src'], $builder->getScanDirs());
        $this->assertEquals(['tests', 'vendor', 'tmp'], $builder->getExcludeDirs());
        $this->assertEquals('2024-11-05', $builder->getProtocolVersion());
        $this->assertEquals(ServerBuilder::DEFAULT_CACHE_ENGINE, $builder->getCacheEngine());
        $this->assertEquals('Adaptic MCP Server', $builder->getServerInfo()['name']);
        $this->assertEquals(SynapsePlugin::VERSION, $builder->getServerInfo()['version']);
        $this->assertEquals(ROOT, $builder->getBasePath());
    }

    /**
     * Test configuration from array
     */
    public function testConfigurationFromArray(): void
    {
        $config = [
            'serverInfo' => ['name' => 'Test Server', 'version' => '2.0.0'],
            'discovery' => [
                'scanDirs' => ['custom', 'paths'],
                'excludeDirs' => ['ignore'],
                'cache' => 'custom_cache',
            ],
            'protocolVersion' => '2024-11-05',
            'basePath' => '/custom/path',
        ];

        $builder = new ServerBuilder($config);

        $this->assertEquals('Test Server', $builder->getServerInfo()['name']);
        $this->assertEquals('2.0.0', $builder->getServerInfo()['version']);
        $this->assertEquals(['custom', 'paths'], $builder->getScanDirs());
        $this->assertEquals(['ignore'], $builder->getExcludeDirs());
        $this->assertEquals('custom_cache', $builder->getCacheEngine());
        $this->assertEquals('/custom/path', $builder->getBasePath());
    }

    /**
     * Test fluent interface
     */
    public function testFluentInterface(): void
    {
        $builder = new ServerBuilder();

        $result = $builder
            ->addScanDirectory('extra')
            ->setProtocolVersion('2024-11-05')
            ->withoutCache();

        $this->assertSame($builder, $result);
        $this->assertContains('extra', $builder->getScanDirs());
        $this->assertNull($builder->getCacheEngine());
    }

    /**
     * Test add scan directory does not duplicate
     */
    public function testAddScanDirectoryDoesNotDuplicate(): void
    {
        $builder = new ServerBuilder(['discovery' => ['scanDirs' => ['src']]]);

        $builder->addScanDirectory('src');
        $builder->addScanDirectory('custom');
        $builder->addScanDirectory('custom');

        $scanDirs = $builder->getScanDirs();
        $this->assertCount(2, $scanDirs);
        $this->assertContains('src', $scanDirs);
        $this->assertContains('custom', $scanDirs);
    }

    /**
     * Test withPluginTools adds plugin directory
     */
    public function testWithPluginToolsAddsPluginDirectory(): void
    {
        $builder = new ServerBuilder(['discovery' => ['scanDirs' => ['src']]]);

        $builder->withPluginTools();

        $scanDirs = $builder->getScanDirs();
        $this->assertGreaterThan(1, count($scanDirs));

        // Plugin Tools, Prompts, and Resources paths should be in scan dirs
        $pluginSrcPath = dirname(dirname(dirname(__DIR__)));
        // Normalize dirname result to forward slashes first
        $pluginSrcPath = str_replace(DIRECTORY_SEPARATOR, '/', $pluginSrcPath);
        $pluginSrcPath .= '/src';
        $pluginSrcPath = ltrim($pluginSrcPath, '/');

        $toolsPath = $pluginSrcPath . '/Tools';
        $promptsPath = $pluginSrcPath . '/Prompts';
        $resourcesPath = $pluginSrcPath . '/Resources';

        $this->assertContains($toolsPath, $scanDirs);
        $this->assertContains($promptsPath, $scanDirs);
        $this->assertContains($resourcesPath, $scanDirs);
    }

    /**
     * Test withPluginTools does not duplicate
     */
    public function testWithPluginToolsDoesNotDuplicate(): void
    {
        $builder = new ServerBuilder(['discovery' => ['scanDirs' => ['src']]]);

        $builder->withPluginTools();
        $builder->withPluginTools();

        $scanDirs = $builder->getScanDirs();
        $pluginSrcPath = dirname(dirname(dirname(__DIR__)));
        // Normalize dirname result to forward slashes first
        $pluginSrcPath = str_replace(DIRECTORY_SEPARATOR, '/', $pluginSrcPath);
        $pluginSrcPath .= '/src';
        $pluginSrcPath = ltrim($pluginSrcPath, '/');

        $toolsPath = $pluginSrcPath . '/Tools';
        $promptsPath = $pluginSrcPath . '/Prompts';
        $resourcesPath = $pluginSrcPath . '/Resources';

        // Count occurrences of each path - should be 1 each
        $toolsCount = count(array_filter($scanDirs, fn(string $dir): bool => $dir === $toolsPath));
        $promptsCount = count(array_filter($scanDirs, fn(string $dir): bool => $dir === $promptsPath));
        $resourcesCount = count(array_filter($scanDirs, fn(string $dir): bool => $dir === $resourcesPath));
        $this->assertEquals(1, $toolsCount);
        $this->assertEquals(1, $promptsCount);
        $this->assertEquals(1, $resourcesCount);
    }

    /**
     * Test getCache returns valid cache
     */
    public function testGetCacheReturnsValidCache(): void
    {
        $builder = new ServerBuilder();
        $cache = $builder->getCache('default');

        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     * Test getCache returns null for invalid engine
     */
    public function testGetCacheReturnsNullForInvalidEngine(): void
    {
        $builder = new ServerBuilder();
        $cache = $builder->getCache('totally_invalid_engine_xyz');

        $this->assertNull($cache);
    }

    /**
     * Test withoutCache sets engine to null
     */
    public function testWithoutCacheSetsEngineToNull(): void
    {
        $builder = new ServerBuilder(['discovery' => ['cache' => 'default']]);

        $this->assertEquals('default', $builder->getCacheEngine());

        $builder->withoutCache();

        $this->assertNull($builder->getCacheEngine());
    }

    /**
     * Test clearCache success
     */
    public function testClearCacheSuccess(): void
    {
        // Add test data
        $cache = Cache::pool('default');
        $testKey = 'builder_test_' . uniqid();
        $cache->set($testKey, 'test_data');
        $this->assertNotNull($cache->get($testKey));

        // Clear cache
        $result = ServerBuilder::clearCache('default');

        $this->assertTrue($result);
        $this->assertNull($cache->get($testKey));
    }

    /**
     * Test clearCache handles invalid engine
     */
    public function testClearCacheHandlesInvalidEngine(): void
    {
        $result = ServerBuilder::clearCache('nonexistent_engine');

        // Should return false but not throw
        $this->assertFalse($result);
    }

    /**
     * Test build creates server instance
     */
    public function testBuildCreatesServerInstance(): void
    {
        $config = Configure::read('Synapse');
        $builder = new ServerBuilder($config);

        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * Test build with cache enabled
     */
    public function testBuildWithCacheEnabled(): void
    {
        $config = Configure::read('Synapse');
        $config['discovery']['cache'] = 'default';

        $builder = new ServerBuilder($config);
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
        $this->assertEquals('default', $builder->getCacheEngine());
    }

    /**
     * Test build with cache disabled
     */
    public function testBuildWithCacheDisabled(): void
    {
        $config = Configure::read('Synapse');

        $builder = (new ServerBuilder($config))->withoutCache();
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
        $this->assertNull($builder->getCacheEngine());
    }

    /**
     * Test build with plugin tools
     */
    public function testBuildWithPluginTools(): void
    {
        $config = Configure::read('Synapse');

        $builder = (new ServerBuilder($config))->withPluginTools();
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);

        // Verify Tools, Prompts, and Resources directories are in scan dirs
        $pluginSrcPath = dirname(dirname(dirname(__DIR__)));
        // Normalize dirname result to forward slashes first
        $pluginSrcPath = str_replace(DIRECTORY_SEPARATOR, '/', $pluginSrcPath);
        $pluginSrcPath .= '/src';
        $pluginSrcPath = ltrim($pluginSrcPath, '/');

        $toolsPath = $pluginSrcPath . '/Tools';
        $promptsPath = $pluginSrcPath . '/Prompts';
        $resourcesPath = $pluginSrcPath . '/Resources';
        $this->assertContains($toolsPath, $builder->getScanDirs());
        $this->assertContains($promptsPath, $builder->getScanDirs());
        $this->assertContains($resourcesPath, $builder->getScanDirs());
    }

    /**
     * Test build with custom configuration
     */
    public function testBuildWithCustomConfiguration(): void
    {
        $config = [
            'serverInfo' => ['name' => 'Custom Server', 'version' => '3.0.0'],
            'discovery' => [
                'scanDirs' => ['app/src', 'plugins'],
                'excludeDirs' => ['tests'],
                'cache' => 'default',
            ],
            'protocolVersion' => '2024-11-05',
        ];

        $builder = new ServerBuilder($config);
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
        $this->assertEquals(['app/src', 'plugins'], $builder->getScanDirs());
        $this->assertEquals(['tests'], $builder->getExcludeDirs());
    }

    /**
     * Test setProtocolVersion
     */
    public function testSetProtocolVersion(): void
    {
        $builder = new ServerBuilder();

        $this->assertEquals('2024-11-05', $builder->getProtocolVersion());

        $builder->setProtocolVersion('2024-11-05');

        $this->assertEquals('2024-11-05', $builder->getProtocolVersion());
    }

    /**
     * Test multiple scan directories can be added
     */
    public function testMultipleScanDirectoriesCanBeAdded(): void
    {
        $builder = new ServerBuilder(['discovery' => ['scanDirs' => ['src']]]);

        $builder
            ->addScanDirectory('custom1')
            ->addScanDirectory('custom2')
            ->addScanDirectory('custom3');

        $scanDirs = $builder->getScanDirs();
        $this->assertCount(4, $scanDirs);
        $this->assertContains('src', $scanDirs);
        $this->assertContains('custom1', $scanDirs);
        $this->assertContains('custom2', $scanDirs);
        $this->assertContains('custom3', $scanDirs);
    }

    /**
     * Test builder with minimal configuration
     */
    public function testBuilderWithMinimalConfiguration(): void
    {
        $builder = new ServerBuilder();
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * Test cache configuration is optional
     */
    public function testCacheConfigurationIsOptional(): void
    {
        $config = [
            'serverInfo' => ['name' => 'Test', 'version' => '1.0.0'],
            'discovery' => [
                'scanDirs' => ['src'],
                'excludeDirs' => ['tests'],
                // No cache key
            ],
        ];

        $builder = new ServerBuilder($config);

        // Should default to DEFAULT_CACHE_ENGINE
        $this->assertEquals(ServerBuilder::DEFAULT_CACHE_ENGINE, $builder->getCacheEngine());
    }

    /**
     * Test builder can be reused to build multiple servers
     */
    public function testBuilderCanBeReusedToBuildMultipleServers(): void
    {
        $builder = new ServerBuilder(Configure::read('Synapse'));

        $server1 = $builder->build();
        $server2 = $builder->build();

        $this->assertInstanceOf(Server::class, $server1);
        $this->assertInstanceOf(Server::class, $server2);
        $this->assertNotSame($server1, $server2);
    }

    /**
     * Test server info defaults when not provided
     */
    public function testServerInfoDefaultsWhenNotProvided(): void
    {
        $config = [
            'discovery' => [
                'scanDirs' => ['src'],
            ],
        ];

        $builder = new ServerBuilder($config);
        $serverInfo = $builder->getServerInfo();

        $this->assertEquals('Adaptic MCP Server', $serverInfo['name']);
        $this->assertEquals(SynapsePlugin::VERSION, $serverInfo['version']);
    }

    /**
     * Test discovery defaults when not provided
     */
    public function testDiscoveryDefaultsWhenNotProvided(): void
    {
        $config = [
            'serverInfo' => ['name' => 'Test', 'version' => '1.0.0'],
        ];

        $builder = new ServerBuilder($config);

        $this->assertEquals(['src'], $builder->getScanDirs());
        $this->assertEquals(['tests', 'vendor', 'tmp'], $builder->getExcludeDirs());
    }

    /**
     * Test base path defaults to ROOT constant
     */
    public function testBasePathDefaultsToRoot(): void
    {
        $builder = new ServerBuilder();

        $this->assertEquals(ROOT, $builder->getBasePath());
    }

    /**
     * Test setContainer
     */
    public function testSetContainer(): void
    {
        $builder = new ServerBuilder();
        $container = $this->getMockBuilder(ContainerInterface::class)->getMock();

        $result = $builder->setContainer($container);

        $this->assertSame($builder, $result);
    }

    /**
     * Test builder with null container
     */
    public function testBuilderWithNullContainer(): void
    {
        $builder = new ServerBuilder();
        $result = $builder->setContainer(null);

        $this->assertSame($builder, $result);

        // Should still build successfully
        $server = $builder->build();
        $this->assertInstanceOf(Server::class, $server);
    }

    /**
     * Test setLogger
     */
    public function testSetLogger(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $builder = new ServerBuilder();

        $result = $builder->setLogger($logger);

        $this->assertSame($builder, $result);
        $this->assertSame($logger, $builder->getLogger());
    }

    /**
     * Test getLogger returns null by default
     */
    public function testGetLoggerReturnsNull(): void
    {
        $builder = new ServerBuilder();

        $this->assertNull($builder->getLogger());
    }

    /**
     * Test setLogger with null
     */
    public function testSetLoggerWithNull(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $builder = new ServerBuilder();

        $builder->setLogger($logger);
        $this->assertSame($logger, $builder->getLogger());

        $builder->setLogger(null);
        $this->assertNull($builder->getLogger());
    }

    /**
     * Test build with logger
     */
    public function testBuildWithLogger(): void
    {
        $logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $config = Configure::read('Synapse');

        $builder = (new ServerBuilder($config))->setLogger($logger);
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
        $this->assertSame($logger, $builder->getLogger());
    }

    /**
     * Test build without logger
     */
    public function testBuildWithoutLogger(): void
    {
        $config = Configure::read('Synapse');

        $builder = new ServerBuilder($config);
        $server = $builder->build();

        $this->assertInstanceOf(Server::class, $server);
        $this->assertNull($builder->getLogger());
    }
}
