<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Command\Command;
use Cake\Console\CommandCollection;
use Cake\Console\ConsoleInputArgument;
use Cake\Console\ConsoleInputOption;
use Cake\Console\ConsoleOptionParser;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Throwable;

/**
 * Command Tools
 *
 * MCP tools for inspecting and discovering CakePHP console commands.
 */
class CommandTools
{
    /**
     * Constructor
     *
     * @param \Cake\Console\CommandCollection|null $commandCollection Command collection to inspect
     */
    public function __construct(
        protected ?CommandCollection $commandCollection = null,
    ) {
        $this->commandCollection ??= new CommandCollection();
    }

    /**
     * Get the CommandCollection for inspection.
     *
     * @return \Cake\Console\CommandCollection The command collection
     */
    protected function getCommandCollection(): CommandCollection
    {
        return $this->commandCollection ?? new CommandCollection();
    }

    /**
     * List all available CakePHP console commands.
     *
     * Returns a list of all registered commands with optional filtering and sorting.
     * Useful for discovering available console commands and understanding their plugins.
     *
     * @param string|null $plugin Filter by plugin name
     * @param string|null $namespace Filter by command namespace
     * @param bool $sort Sort commands alphabetically by name
     * @return array<string, mixed> List of commands with metadata
     */
    #[McpTool(
        name: 'list_commands',
        description: 'List all available CakePHP console commands with optional filtering and sorting',
    )]
    public function listCommands(
        ?string $plugin = null,
        ?string $namespace = null,
        bool $sort = false,
    ): array {
        $commandsList = [];

            $commandCollection = $this->getCommandCollection();

        foreach ($commandCollection as $name => $command) {
            $commandClass = is_string($command) ? $command : $command::class;
            $commandNamespace = $this->extractNamespace($commandClass);
            $commandPlugin = $this->extractPlugin($commandNamespace);

            // Apply plugin filter
            if ($plugin !== null && $commandPlugin !== $plugin) {
                continue;
            }

            // Apply namespace filter
            if ($namespace !== null && !str_starts_with($commandNamespace, $namespace)) {
                continue;
            }

            // Try to get description, but handle commands that can't be instantiated
            $description = null;
            try {
                if (is_string($command)) {
                    // Some commands require constructor arguments, wrap in try-catch
                    $commandToDescribe = new $command();
                } else {
                    $commandToDescribe = $command;
                }

                if ($commandToDescribe instanceof Command) {
                    $description = $this->getCommandDescription($commandToDescribe);
                }
            } catch (Throwable) {
                // Command couldn't be instantiated, description will be null
            }

            $commandsList[] = [
            'name' => $name,
            'class' => $commandClass,
            'namespace' => $commandNamespace,
            'plugin' => $commandPlugin,
            'description' => $description,
            ];
        }

        if ($sort) {
            usort($commandsList, function (array $a, array $b): int {
                return strcasecmp($a['name'], $b['name']);
            });
        }

        return [
            'total' => count($commandsList),
            'commands' => $commandsList,
        ];
    }

    /**
     * Get detailed information about a specific console command.
     *
     * Returns comprehensive information about a command including its description,
     * usage, options, arguments, and their details. Options include short names,
     * help text, defaults, and choices. Arguments include help text, required status.
     *
     * @param string $name The command name to look up (e.g., 'migrations list')
     * @return array<string, mixed> Command details including options and arguments
     */
    #[McpTool(
        name: 'get_command_info',
        description: 'Get detailed information about a specific CakePHP console command',
    )]
    public function getCommandInfo(string $name): array
    {
        $commandCollection = $this->getCommandCollection();
        if (!$commandCollection->has($name)) {
            throw new ToolCallException(sprintf("Command '%s' not found", $name));
        }

        $command = $commandCollection->get($name);
        $commandClass = is_string($command) ? $command : $command::class;
        $commandNamespace = $this->extractNamespace($commandClass);
        $commandPlugin = $this->extractPlugin($commandNamespace);

        // Try to get the command instance to access option parser
        $commandInstance = $this->instantiateCommand($command);

        // If command couldn't be instantiated, return basic info
        if (!$commandInstance instanceof Command) {
            return [
                'name' => $name,
                'class' => $commandClass,
                'namespace' => $commandNamespace,
                'plugin' => $commandPlugin,
                'description' => null,
                'help' => null,
                'usage' => null,
                'options' => [],
                'arguments' => [],
            ];
        }

        $optionParser = $commandInstance->getOptionParser();

        return [
            'name' => $name,
            'class' => $commandClass,
            'namespace' => $commandNamespace,
            'plugin' => $commandPlugin,
            'description' => $this->getCommandDescription($commandInstance),
            'help' => $optionParser->help(),
            'usage' => null,
            'options' => $this->parseOptions($optionParser),
            'arguments' => $this->parseArguments($optionParser),
        ];
    }

    /**
     * Extract the namespace from a fully qualified class name.
     *
     * @param string $className Fully qualified class name
     * @return string Namespace without class name
     */
    private function extractNamespace(string $className): string
    {
        $parts = explode('\\', $className);
        array_pop($parts); // Remove class name

        return implode('\\', $parts);
    }

    /**
     * Extract plugin name from namespace.
     *
     * Assumes plugin commands are in a vendor namespace. Skips common app namespaces
     * like App, Synapse, and TestApp.
     *
     * @param string $namespace Full namespace
     * @return string|null Plugin name or null if not a plugin command
     */
    private function extractPlugin(string $namespace): ?string
    {
        $parts = explode('\\', $namespace);

        if (count($parts) < 2) {
            return null;
        }

        $firstPart = $parts[0];

        // Skip common app namespaces
        if (in_array($firstPart, ['App', 'Synapse', 'TestApp'], true)) {
            return null;
        }

        return $parts[1];
    }

    /**
     * Get description from a command.
     *
     * Attempts to get the description from the command's option parser.
     *
     * @param \Cake\Command\Command $command Command instance
     * @return string|null Command description
     */
    private function getCommandDescription(Command $command): ?string
    {
        try {
            $optionParser = $command->getOptionParser();

            return $optionParser->getDescription();
        } catch (Throwable) {
            // Return null if description cannot be retrieved
            return null;
        }
    }

    /**
     * Instantiate a command if given a class name string or CommandInterface.
     *
     * Returns null if the command cannot be instantiated (e.g., requires constructor arguments).
     *
     * @param \Cake\Command\Command|\Cake\Console\CommandInterface|string $command Command instance or class name
     * @return \Cake\Command\Command|null Command instance or null if instantiation fails
     */
    private function instantiateCommand(mixed $command): ?Command
    {
        try {
            if ($command instanceof Command) {
                return $command;
            }

            if (is_string($command)) {
                $instance = new $command();

                return $instance instanceof Command ? $instance : null;
            }

            // CommandInterface that's not a Command - instantiate by class name
            $className = $command::class;
            $instance = new $className();

            return $instance instanceof Command ? $instance : null;
        } catch (Throwable) {
            // Command requires constructor arguments or can't be instantiated
            return null;
        }
    }

    /**
     * Parse options from console option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return array<int, array<string, mixed>> List of options with details
     */
    private function parseOptions(ConsoleOptionParser $parser): array
    {
        $options = [];

        try {
            $parserOptions = $parser->options();

            foreach ($parserOptions as $option) {
                if ($option instanceof ConsoleInputOption) {
                    $options[] = [
                        'name' => $option->name(),
                        'short' => $option->short(),
                        'help' => $option->help(),
                        'default' => $option->defaultValue(),
                        'boolean' => $option->isBoolean(),
                        'choices' => $option->choices(),
                    ];
                }
            }
        } catch (Throwable) {
            // If parsing fails, return empty array
        }

        return $options;
    }

    /**
     * Parse arguments from console option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     * @return array<int, array<string, mixed>> List of arguments with details
     */
    private function parseArguments(ConsoleOptionParser $parser): array
    {
        $arguments = [];

        try {
            $parserArguments = $parser->arguments();

            // arguments() returns an indexed array of ConsoleInputArgument objects
            foreach ($parserArguments as $argument) {
                if ($argument instanceof ConsoleInputArgument) {
                    $arguments[] = [
                        'name' => $argument->name(),
                        'help' => $argument->help(),
                        'required' => $argument->isRequired(),
                    ];
                }
            }
        } catch (Throwable) {
            // If parsing fails, return empty array
        }

        return $arguments;
    }
}
