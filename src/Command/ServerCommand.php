<?php
declare(strict_types=1);

namespace Synapse\Command;

use Cake\Cache\Cache;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\CommandFactoryInterface;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
use Psr\SimpleCache\CacheInterface;
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
     * Default cache engine name for discovery caching
     */
    private const DEFAULT_CACHE_ENGINE = 'default';

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
            // Handle cache clearing if requested
            if ($args->getOption('clear-cache')) {
                $this->clearDiscoveryCache($io);
            }

            $io->out('<info>Building MCP server...</info>');
            $server = $this->buildServer($io, $args);

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

    /**
     * Build and configure the MCP server
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @param \Cake\Console\Arguments $args Command arguments
     */
    private function buildServer(ConsoleIo $io, Arguments $args): Server
    {
        $config = Configure::read('Synapse', []);
        $serverInfo = $config['serverInfo'] ?? [
            'name' => 'Adaptic MCP Server',
            'version' => '1.0.0',
        ];

        $discoveryConfig = $config['discovery'] ?? [];
        $scanDirs = $discoveryConfig['scanDirs'] ?? ['src'];
        $excludeDirs = $discoveryConfig['excludeDirs'] ?? ['tests', 'vendor', 'tmp'];

        // Get protocol version from config
        $protocolVersionString = $config['protocolVersion'] ?? '2024-11-05';
        $protocolVersion = ProtocolVersion::from($protocolVersionString);

        $io->verbose(sprintf(
            'Discovery: scanning %s, excluding %s',
            implode(', ', $scanDirs),
            implode(', ', $excludeDirs),
        ));

        // Build server using the official SDK
        $builder = Server::builder()
            ->setServerInfo($serverInfo['name'], $serverInfo['version'])
            ->setProtocolVersion($protocolVersion);

        // Configure discovery with optional caching
        $cache = null;
        $cacheDisabled = $args->getOption('no-cache');

        if (!$cacheDisabled) {
            $cacheEngine = $discoveryConfig['cache'] ?? self::DEFAULT_CACHE_ENGINE;
            $cache = $this->getCache($cacheEngine, $io);

            if ($cache instanceof CacheInterface) {
                $io->verbose(sprintf('<info>Discovery caching enabled (using: %s)</info>', $cacheEngine));
            }
        } else {
            $io->verbose('<comment>Discovery caching disabled via --no-cache</comment>');
        }

        $builder->setDiscovery(
            basePath: ROOT,
            scanDirs: $scanDirs,
            excludeDirs: $excludeDirs,
            cache: $cache,
        );

        // Set container from DI
        $builder->setContainer($this->container);

        $io->out('<info>Discovering MCP elements...</info>');
        $server = $builder->build();

        $io->verbose('<success>Discovery complete</success>');

        return $server;
    }

    /**
     * Get PSR-16 cache instance from CakePHP cache configuration
     *
     * @param string $engineName Cache engine name from config/app.php
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function getCache(string $engineName, ConsoleIo $io): ?CacheInterface
    {
        try {
            // Get PSR-16 compatible cache pool from CakePHP
            $cache = Cache::pool($engineName);

            return $cache;
        } catch (Throwable $throwable) {
            $io->warning(sprintf(
                'Failed to initialize cache "%s": %s',
                $engineName,
                $throwable->getMessage(),
            ));

            return null;
        }
    }

    /**
     * Clear the discovery cache
     *
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function clearDiscoveryCache(ConsoleIo $io): void
    {
        $config = Configure::read('Synapse.discovery', []);
        $cacheEngine = $config['cache'] ?? self::DEFAULT_CACHE_ENGINE;

        try {
            if (Cache::clear($cacheEngine)) {
                $io->success(sprintf('Discovery cache cleared (engine: %s)', $cacheEngine));
            } else {
                $io->warning(sprintf('Failed to clear cache (engine: %s)', $cacheEngine));
            }
        } catch (Throwable $throwable) {
            $io->error(sprintf('Error clearing cache: %s', $throwable->getMessage()));
        }
    }
}
