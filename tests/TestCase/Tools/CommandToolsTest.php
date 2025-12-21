<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\Console\CommandCollection;
use Cake\Core\Container;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\SynapsePlugin;
use Synapse\Tools\CommandTools;
use TestApp\Command\AnotherTestCommand;
use TestApp\Command\TestCommand;

/**
 * CommandTools Test Case
 *
 * Tests for command inspection and discovery tools.
 */
class CommandToolsTest extends TestCase
{
    private CommandTools $commandTools;

    private CommandCollection $commandCollection;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a command collection with test commands
        $this->commandCollection = new CommandCollection();

        // Add test app commands
        $this->commandCollection->add('test_command', TestCommand::class);
        $this->commandCollection->add('another_test', AnotherTestCommand::class);

        $this->commandTools = new CommandTools($this->commandCollection);

        // Simulate the Console.buildCommands event to populate the static cache
        $event = new Event('Console.buildCommands', $this->commandCollection);
        EventManager::instance()->dispatch($event);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->commandTools);
        unset($this->commandCollection);
        parent::tearDown();
    }

    /**
     * Test listCommands without filters
     */
    public function testListCommandsWithoutFilters(): void
    {
        $result = $this->commandTools->listCommands();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('commands', $result);
        $this->assertGreaterThanOrEqual(2, $result['total']);
        $this->assertIsArray($result['commands']);

        // Check first command structure
        $firstCommand = $result['commands'][0];
        $this->assertArrayHasKey('name', $firstCommand);
        $this->assertArrayHasKey('class', $firstCommand);
        $this->assertArrayHasKey('namespace', $firstCommand);
        $this->assertArrayHasKey('plugin', $firstCommand);
        $this->assertArrayHasKey('description', $firstCommand);
    }

    /**
     * Test listCommands with sort
     */
    public function testListCommandsWithSort(): void
    {
        $result = $this->commandTools->listCommands(sort: true);

        $this->assertArrayHasKey('commands', $result);
        $commands = $result['commands'];

        // Verify sorting
        $commandsCount = count($commands);
        for ($i = 0; $i < $commandsCount - 1; $i++) {
            $current = strtolower($commands[$i]['name']);
            $next = strtolower($commands[$i + 1]['name']);
            $this->assertLessThanOrEqual(0, strcmp($current, $next));
        }
    }

    /**
     * Test listCommands with namespace filter
     */
    public function testListCommandsWithNamespaceFilter(): void
    {
        $result = $this->commandTools->listCommands(namespace: 'TestApp\\Command');

        $this->assertArrayHasKey('commands', $result);
        $this->assertGreaterThanOrEqual(2, $result['total']);

        // Verify all returned commands have the filtered namespace
        foreach ($result['commands'] as $command) {
            $this->assertStringContainsString('TestApp\\Command', $command['namespace']);
        }
    }

    /**
     * Test listCommands with non-matching filter
     */
    public function testListCommandsWithNonMatchingFilter(): void
    {
        $result = $this->commandTools->listCommands(namespace: 'NonExistent\\Namespace');

        $this->assertArrayHasKey('commands', $result);
        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['commands']);
    }

    /**
     * Test getCommandInfo for existing command
     */
    public function testGetCommandInfoForExistingCommand(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('class', $result);
        $this->assertArrayHasKey('namespace', $result);
        $this->assertArrayHasKey('plugin', $result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('help', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('arguments', $result);

        $this->assertEquals('test_command', $result['name']);
        $this->assertIsArray($result['options']);
        $this->assertIsArray($result['arguments']);
    }

    /**
     * Test getCommandInfo for non-existent command
     */
    public function testGetCommandInfoForNonExistentCommand(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage("Command 'non_existent' not found");

        $this->commandTools->getCommandInfo('non_existent');
    }

    /**
     * Test getCommandInfo returns correct command class
     */
    public function testGetCommandInfoReturnsCorrectClass(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertEquals(TestCommand::class, $result['class']);
    }

    /**
     * Test that CommandTools can be instantiated
     */
    public function testCommandToolsCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CommandTools::class, $this->commandTools);
    }

    /**
     * Test listCommands returns command names
     */
    public function testListCommandsReturnsCommandNames(): void
    {
        $result = $this->commandTools->listCommands();

        $commandNames = array_column($result['commands'], 'name');

        $this->assertContains('test_command', $commandNames);
        $this->assertContains('another_test', $commandNames);
    }

    /**
     * Test getCommandInfo includes options
     */
    public function testGetCommandInfoIncludesOptions(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertIsArray($result['options']);
        // The TestCommand has an 'output-format' option (plus default options)
        $optionNames = array_column($result['options'], 'name');
        $this->assertContains('output-format', $optionNames);
    }

    /**
     * Test getCommandInfo options have required structure
     */
    public function testGetCommandInfoOptionsHaveRequiredStructure(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertNotEmpty($result['options']);
        $firstOption = $result['options'][0];
        $this->assertArrayHasKey('name', $firstOption);
        $this->assertArrayHasKey('short', $firstOption);
        $this->assertArrayHasKey('help', $firstOption);
        $this->assertArrayHasKey('default', $firstOption);
        $this->assertArrayHasKey('boolean', $firstOption);
    }

    /**
     * Test getCommandInfo arguments have required structure
     */
    public function testGetCommandInfoArgumentsHaveRequiredStructure(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        // TestCommand has 'input' argument
        $this->assertNotEmpty($result['arguments']);
        $firstArg = $result['arguments'][0];
        $this->assertArrayHasKey('name', $firstArg);
        $this->assertArrayHasKey('help', $firstArg);
        $this->assertArrayHasKey('required', $firstArg);
    }

    /**
     * Test getCommandInfo with command that has arguments
     */
    public function testGetCommandInfoWithTestCommandArguments(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertNotEmpty($result['arguments']);
        $argumentNames = array_column($result['arguments'], 'name');
        $this->assertContains('name', $argumentNames);
    }

    /**
     * Test getCommandInfo with arguments
     */
    public function testGetCommandInfoWithArguments(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertNotEmpty($result['arguments']);
        $argumentNames = array_column($result['arguments'], 'name');
        $this->assertContains('name', $argumentNames);
    }

    /**
     * Test listCommands total count matches commands array
     */
    public function testListCommandsTotalCountMatches(): void
    {
        $result = $this->commandTools->listCommands();

        $this->assertEquals(count($result['commands']), $result['total']);
    }

    /**
     * Test listCommands with plugin filter
     */
    public function testListCommandsWithPluginFilter(): void
    {
        $result = $this->commandTools->listCommands(plugin: 'NonExistentPlugin');

        $this->assertArrayHasKey('commands', $result);
        $this->assertEquals(0, $result['total']);
    }

    /**
     * Test getCommandInfo returns correct description
     */
    public function testGetCommandInfoReturnsCorrectDescription(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertEquals('A test command for unit testing', $result['description']);
    }

    /**
     * Test listCommands with sort and namespace filter
     */
    public function testListCommandsWithSortAndNamespaceFilter(): void
    {
        $result = $this->commandTools->listCommands(
            namespace: 'TestApp\\Command',
            sort: true,
        );

        $this->assertGreaterThanOrEqual(1, $result['total']);

        $commands = $result['commands'];
        $commandsCount = count($commands);
        for ($i = 0; $i < $commandsCount - 1; $i++) {
            $current = strtolower($commands[$i]['name']);
            $next = strtolower($commands[$i + 1]['name']);
            $this->assertLessThanOrEqual(0, strcmp($current, $next));
        }
    }

    /**
     * Test CommandTools receives CommandCollection via DI container
     *
     * Simulates the real-world flow where:
     * 1. Plugin registers services and events
     * 2. Console.buildCommands event fires with CommandCollection
     * 3. Container resolves CommandTools with injected collection
     */
    public function testCommandToolsReceivesCollectionViaDI(): void
    {
        SynapsePlugin::resetCommandCollection();

        $container = new Container();
        $eventManager = new EventManager();

        $plugin = new SynapsePlugin();
        $plugin->services($container);
        $plugin->events($eventManager);

        // Dispatch event with commands
        $commands = new CommandCollection();
        $commands->add('test_command', TestCommand::class);
        $commands->add('another_test', AnotherTestCommand::class);

        $event = new Event('Console.buildCommands', null, ['commands' => $commands]);
        $eventManager->dispatch($event);

        // Resolve CommandTools from container (as MCP SDK would)
        $this->assertTrue($container->has(CommandTools::class));
        $commandTools = $container->get(CommandTools::class);

        $result = $commandTools->listCommands();

        $this->assertGreaterThanOrEqual(2, $result['total']);
        $commandNames = array_column($result['commands'], 'name');
        $this->assertContains('test_command', $commandNames);
        $this->assertContains('another_test', $commandNames);
    }

    /**
     * Test CommandTools falls back to empty collection when event not fired
     */
    public function testCommandToolsFallbackWithoutEvent(): void
    {
        SynapsePlugin::resetCommandCollection();

        $container = new Container();
        $eventManager = new EventManager();

        $plugin = new SynapsePlugin();
        $plugin->services($container);
        $plugin->events($eventManager);

        // Don't dispatch event - simulate case where collection not captured
        $commandTools = $container->get(CommandTools::class);
        $result = $commandTools->listCommands();

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['commands']);
    }

    /**
     * Test getCommandInfo throws exception for non-existent command
     */
    public function testGetCommandInfoThrowsForNonExistentCommand(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage("Command 'nonexistent_command_xyz' not found");

        $this->commandTools->getCommandInfo('nonexistent_command_xyz');
    }

    /**
     * Test getCommandInfo includes namespace information
     */
    public function testGetCommandInfoIncludesNamespaceInfo(): void
    {
        $result = $this->commandTools->getCommandInfo('test_command');

        $this->assertArrayHasKey('namespace', $result);
        $this->assertIsString($result['namespace']);
        $this->assertStringContainsString('TestApp', $result['namespace']);
    }
}
