<?php
declare(strict_types=1);

namespace Synapse\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Log\Log;
use Mcp\Server\Transport\StdioTransport;
use Psr\Log\NullLogger;
use Synapse\Builder\ServerBuilder;
use Throwable;

/**
 * MCP Server Command
 *
 * Starts the Model Context Protocol server with the specified transport.
 * The server exposes CakePHP functionality (Tools, Resources, Prompts) to MCP clients.
 */
class ServerCommand extends Command
{
    /**
     * Internal environment flag used to prime Inspector's browser event stream.
     */
    private const INSPECTOR_BOOTSTRAP_ENV = 'SYNAPSE_INSPECTOR_BOOTSTRAP';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse server';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Start the MCP (Model Context Protocol) server';
    }

    /**
     * Constructor
     *
     * @param \Cake\Core\ContainerInterface $container CakePHP DI container
     * @param \Cake\Console\CommandFactoryInterface|null $factory Command factory
     */
    public function __construct(
        private ContainerInterface $container,
        ?CommandFactoryInterface $factory = null,
    ) {
        parent::__construct($factory);
    }

    /**
     * Configure command options
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Start the MCP (Model Context Protocol) server')
            ->addOption('transport', [
                'short' => 't',
                'help' => 'Transport type (currently only stdio is supported)',
                'default' => 'stdio',
                'choices' => ['stdio'],
            ])
            ->addOption('no-cache', [
                'short' => 'n',
                'help' => 'Disable discovery caching for this run',
                'boolean' => true,
            ])
            ->addOption('clear-cache', [
                'short' => 'c',
                'help' => 'Clear discovery cache before starting',
                'boolean' => true,
            ])
            ->addOption('inspect', [
                'short' => 'i',
                'help' => 'Launch MCP Inspector to test the server interactively (requires Node.js/npx)',
                'boolean' => true,
            ]);

        return $parser;
    }

    /**
     * Execute the command
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @return int Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        // If --inspect flag is present, launch inspector
        if ($args->getOption('inspect')) {
            return $this->launchInspector($args, $io);
        }

        $config = Configure::read('Synapse', []);

        $logEngine = $config['logger'];
        $logger = new NullLogger();

        // Use stderr in verbose mode so we can see what's happening.
        // Note that this overrides the configured log engine.
        if ($args->getOption('verbose')) {
            $logEngine = 'stderr';
        }

        if ($logEngine !== null && is_string($logEngine)) {
            $logger = Log::engine($logEngine) ?: $logger;
        }

        try {
            // Handle cache clearing if requested
            if ($args->getOption('clear-cache')) {
                $cacheEngine = $config['discovery']['cache'] ?? ServerBuilder::DEFAULT_CACHE_ENGINE;

                if (ServerBuilder::clearCache($cacheEngine)) {
                    $logger->info(
                        sprintf('Discovery cache cleared (cache engine: %s)', $cacheEngine),
                    );
                } else {
                    $logger->warning(
                        sprintf('Failed to clear discovery cache (cache engine: %s)', $cacheEngine),
                    );
                }
            }

            $logger->info('Building MCP server...');

            // Build server using ServerBuilder
            $builder = (new ServerBuilder($config))
                ->setContainer($this->container)
                ->setLogger($logger)
                ->withPluginTools();

            // Disable cache if --no-cache flag
            if ($args->getOption('no-cache')) {
                $builder->withoutCache();
                $logger->debug('Discovery caching disabled via --no-cache');
            } else {
                $cacheEngine = $config['discovery']['cache'] ?? ServerBuilder::DEFAULT_CACHE_ENGINE;
                $logger->debug(
                    sprintf('Discovery caching enabled (cache engine: %s)', $cacheEngine),
                );
            }

            // Log discovery configuration
            $logger->debug(
                sprintf(
                    'Discovery: scanning %s, excluding %s',
                    implode(', ', $builder->getScanDirs()),
                    implode(', ', $builder->getExcludeDirs()),
                ),
            );

            $logger->info('Discovering MCP elements...');

            $server = $builder->build();

            $this->emitInspectorBootstrap();

            $logger->info('Discovery complete');
            $logger->info('MCP server started with stdio transport');
            $logger->info('Listening for MCP requests...');

            // Start server (blocking call)
            $stdioTransport = new StdioTransport();
            $server->run($stdioTransport);

            return static::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            $logger->error('MCP Server error: ' . $throwable->getMessage());

            return static::CODE_ERROR;
        }
    }

    /**
     * Emit the Inspector bootstrap event without changing MCP stdout.
     *
     * Inspector's browser proxy needs an initial event on its event stream
     * before the browser can send the MCP initialize request. This diagnostic
     * is opt-in and is intentionally written to stderr only.
     */
    private function emitInspectorBootstrap(): void
    {
        if (getenv(self::INSPECTOR_BOOTSTRAP_ENV) !== '1') {
            return;
        }

        fwrite(STDERR, "Synapse Inspector bootstrap ready\n");
    }

    /**
     * Launch MCP Inspector to test the server
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @return int Exit code
     */
    private function launchInspector(Arguments $args, ConsoleIo $io): int
    {
        $io->out('<info>Launching MCP Inspector...</info>');
        $io->out('');

        // Check if npx is available
        $npxName = DIRECTORY_SEPARATOR === '\\' ? 'npx.cmd' : 'npx';
        $npxPath = $this->findExecutable($npxName);
        if ($npxPath === null) {
            $io->error('npx not found. Please install Node.js to use the MCP Inspector.');
            $io->out('Visit: https://nodejs.org/');

            return static::CODE_ERROR;
        }

        $command = $this->buildInspectorCommand($args, $npxPath);

        $io->out('<info>Command:</info> ' . $command);
        $io->out('');
        $io->out('The inspector will open in your browser...');
        $io->out('Press <warning>Ctrl+C</warning> to stop');
        $io->out('');

        // Execute and return exit code
        passthru($command, $exitCode);

        return $exitCode === 0 ? static::CODE_SUCCESS : static::CODE_ERROR;
    }

    /**
     * Build the command used by MCP Inspector to launch the CakePHP server.
     *
     * Inspector starts stdio servers without a shell. Passing the PHP entrypoint
     * directly avoids relying on the executable bit or shell-script handling of
     * the application's bin/cake wrapper. Changing to the application root
     * before launching Inspector keeps CakePHP application discovery stable.
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param string $npxPath Absolute path to npx
     * @return string Shell command for MCP Inspector
     */
    private function buildInspectorCommand(Arguments $args, string $npxPath): string
    {
        $applicationRoot = defined('ROOT') ? ROOT : (string)getcwd();
        $applicationRoot = realpath($applicationRoot) ?: $applicationRoot;

        $cakeScript = $applicationRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'cake.php';

        $serverCommand = [PHP_BINARY, $cakeScript];
        if (!is_file($cakeScript)) {
            $cakeExecutable = $applicationRoot . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'cake';
            $serverCommand = [$cakeExecutable];
        }

        $serverCommand[] = 'synapse';
        $serverCommand[] = 'server';

        foreach (['clear-cache', 'no-cache', 'verbose', 'quiet'] as $option) {
            if ($args->getOption($option)) {
                $serverCommand[] = '--' . $option;
            }
        }

        $command = [
            escapeshellarg($npxPath),
            escapeshellarg('@modelcontextprotocol/inspector'),
            '--cwd',
            escapeshellarg($applicationRoot),
            '-e',
            escapeshellarg(self::INSPECTOR_BOOTSTRAP_ENV . '=1'),
            '--',
        ];

        foreach ($serverCommand as $argument) {
            $command[] = escapeshellarg($argument);
        }

        return implode(' ', $command);
    }

    /**
     * Find executable in system PATH
     *
     * @param string $name Executable name
     * @return string|null Full path to executable or null if not found
     */
    private function findExecutable(string $name): ?string
    {
        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'nul' : '/dev/null';

        // Try which command first (Unix/Linux/macOS)
        $which = trim((string)shell_exec(sprintf('which %s 2>%s', escapeshellarg($name), $nullDevice)));
        if ($which !== '' && is_executable($which)) {
            return $which;
        }

        // Try where command (Windows)
        $where = trim((string)shell_exec(sprintf('where %s 2>%s', escapeshellarg($name), $nullDevice)));
        if ($where !== '') {
            $paths = explode("\n", $where);
            $firstPath = trim($paths[0]);
            if ($firstPath !== '' && (is_executable($firstPath) || DIRECTORY_SEPARATOR === '\\')) {
                return $firstPath;
            }
        }

        return null;
    }
}
