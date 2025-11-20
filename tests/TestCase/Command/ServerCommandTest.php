<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

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
}
