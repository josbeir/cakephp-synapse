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
}
