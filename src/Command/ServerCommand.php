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
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse server';
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
}
