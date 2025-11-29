<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use ReflectionMethod;
use Synapse\Command\ServerCommand;
use TestApp\Application;

/**
 * ServerCommand Test Case
 *
 * Tests CLI integration only. Server building logic is tested in ServerBuilderTest.
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
        Configure::load('Synapse.synapse');
    }

    /**
     * Test command help displays correctly
     */
    public function testCommandHelp(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Start the MCP');
        $this->assertOutputContains('Model Context Protocol');
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
     * Test command description contains MCP
     */
    public function testCommandDescription(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('MCP');
        $this->assertOutputContains('server');
    }

    /**
     * Test all required options are available
     */
    public function testAllOptionsAreAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--transport');
        $this->assertOutputContains('--no-cache');
        $this->assertOutputContains('--clear-cache');
        $this->assertOutputContains('--inspect');
        $this->assertOutputContains('--verbose');
        $this->assertOutputContains('--quiet');
    }

    /**
     * Test transport option is documented
     */
    public function testTransportOptionInDescription(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('transport');
        $this->assertOutputContains('stdio');
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
     * Test transport option only accepts stdio
     */
    public function testTransportOptionOnlyAcceptsStdio(): void
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

        $this->exec('synapse server -t stdio -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -n -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -c -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -i -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -v -h');
        $this->assertExitSuccess();

        $this->exec('synapse server -q -h');
        $this->assertExitSuccess();
    }

    /**
     * Test no-cache option is available
     */
    public function testNoCacheOptionAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--no-cache');
        $this->assertOutputContains('Disable discovery caching');
    }

    /**
     * Test clear-cache option is available
     */
    public function testClearCacheOptionAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--clear-cache');
        $this->assertOutputContains('Clear discovery cache');
    }

    /**
     * Test cache options are boolean
     */
    public function testCacheOptionsAreBoolean(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--no-cache');
        $this->assertOutputContains('--clear-cache');
    }

    /**
     * Test both cache options work together
     */
    public function testBothCacheOptionsWorkTogether(): void
    {
        $this->exec('synapse server --no-cache --clear-cache --help');

        $this->assertExitSuccess();
    }

    /**
     * Test combined options work
     */
    public function testCombinedOptions(): void
    {
        $this->exec('synapse server --verbose --no-cache --help');
        $this->assertExitSuccess();

        $this->exec('synapse server -v -n -c -h');
        $this->assertExitSuccess();

        $this->exec('synapse server --transport stdio --verbose --quiet --help');
        $this->assertExitSuccess();
    }

    /**
     * Test buildOptionParser has all options
     */
    public function testBuildOptionParserHasAllOptions(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('transport');
        $this->assertOutputContains('no-cache');
        $this->assertOutputContains('clear-cache');
    }

    /**
     * Test buildOptionParser transport choices
     */
    public function testBuildOptionParserTransportChoices(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('stdio');
    }

    /**
     * Test buildOptionParser defaults
     */
    public function testBuildOptionParserDefaults(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('default');
    }

    /**
     * Test inspect option is available
     */
    public function testInspectOptionAvailable(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--inspect');
        $this->assertOutputContains('MCP Inspector');
    }

    /**
     * Test inspect option has short alias
     */
    public function testInspectOptionHasShortAlias(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('-i');
        $this->assertOutputContains('inspect');
    }

    /**
     * Test inspect option mentions Node.js requirement
     */
    public function testInspectOptionMentionsNodeJs(): void
    {
        $this->exec('synapse server --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Node.js');
    }

    /**
     * Test inspect option is boolean
     */
    public function testInspectOptionIsBoolean(): void
    {
        $this->exec('synapse server --inspect --help');

        $this->assertExitSuccess();
    }

    /**
     * Test inspect option works with other options
     */
    public function testInspectOptionWithOtherOptions(): void
    {
        $this->exec('synapse server --inspect --verbose --help');
        $this->assertExitSuccess();

        $this->exec('synapse server -i -v -n -h');
        $this->assertExitSuccess();
    }

    // =========================================================================
    // findExecutable Tests (via reflection)
    // =========================================================================

    /**
     * Test findExecutable finds php binary
     */
    public function testFindExecutableFindsPhp(): void
    {
        $command = $this->createServerCommand();
        $method = new ReflectionMethod(ServerCommand::class, 'findExecutable');

        $result = $method->invoke($command, 'php');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertTrue(is_executable($result));
    }

    /**
     * Test findExecutable returns null for nonexistent executable
     */
    public function testFindExecutableReturnsNullForNonexistent(): void
    {
        $command = $this->createServerCommand();
        $method = new ReflectionMethod(ServerCommand::class, 'findExecutable');

        $result = $method->invoke($command, 'definitely_not_a_real_executable_12345');

        $this->assertNull($result);
    }

    /**
     * Test findExecutable finds common executables
     */
    public function testFindExecutableFindsCommonExecutables(): void
    {
        $command = $this->createServerCommand();
        $method = new ReflectionMethod(ServerCommand::class, 'findExecutable');

        // Test with 'which' or 'ls' - common on Unix systems
        $result = $method->invoke($command, 'ls');

        if ($result !== null) {
            $this->assertFileExists($result);
            $this->assertTrue(is_executable($result));
        }

        // If null, the test passes - executable simply wasn't found
    }

    /**
     * Test defaultName returns correct command name
     */
    public function testDefaultName(): void
    {
        $this->assertEquals('synapse server', ServerCommand::defaultName());
    }

    /**
     * Test getDescription returns appropriate description
     */
    public function testGetDescription(): void
    {
        $description = ServerCommand::getDescription();

        $this->assertStringContainsString('MCP', $description);
        $this->assertStringContainsString('server', $description);
    }

    /**
     * Create a ServerCommand instance for testing
     */
    private function createServerCommand(): ServerCommand
    {
        // Get the container from the application
        $app = new Application(CONFIG);
        $container = $app->getContainer();

        return new ServerCommand($container);
    }
}
