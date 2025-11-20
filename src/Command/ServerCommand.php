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
use Mcp\Server\Transport\StdioTransport;
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
        try {
            $config = Configure::read('Synapse', []);

            // Handle cache clearing if requested
            if ($args->getOption('clear-cache')) {
                $cacheEngine = $config['discovery']['cache'] ?? ServerBuilder::DEFAULT_CACHE_ENGINE;

                if (ServerBuilder::clearCache($cacheEngine)) {
                    $io->success(sprintf('Discovery cache cleared (engine: %s)', $cacheEngine));
                } else {
                    $io->warning(sprintf('Failed to clear cache (engine: %s)', $cacheEngine));
                }
            }

            $io->out('<info>Building MCP server...</info>');

            // Build server using ServerBuilder
            $builder = (new ServerBuilder($config))
                ->setContainer($this->container)
                ->withPluginTools();

            // Disable cache if --no-cache flag
            if ($args->getOption('no-cache')) {
                $builder->withoutCache();
                $io->verbose('<comment>Discovery caching disabled via --no-cache</comment>');
            } else {
                $cacheEngine = $config['discovery']['cache'] ?? ServerBuilder::DEFAULT_CACHE_ENGINE;
                $io->verbose(sprintf('<info>Discovery caching enabled (using: %s)</info>', $cacheEngine));
            }

            // Log discovery configuration
            $io->verbose(sprintf(
                'Discovery: scanning %s, excluding %s',
                implode(', ', $builder->getScanDirs()),
                implode(', ', $builder->getExcludeDirs()),
            ));

            $io->out('<info>Discovering MCP elements...</info>');
            $server = $builder->build();
            $io->verbose('<success>Discovery complete</success>');

            $io->out('<success>✓ MCP server started with stdio transport</success>');
            $io->out('<comment>Listening for MCP requests...</comment>');

            // Start server (blocking call)
            $transport = new StdioTransport();
            $server->run($transport);

            return static::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            $io->error('Server error: ' . $throwable->getMessage());
            $io->err($throwable->getTraceAsString());

            return static::CODE_ERROR;
        }
    }
}
