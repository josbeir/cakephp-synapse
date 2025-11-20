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
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;
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
            $io->out('<info>Building MCP server...</info>');
            $server = $this->buildServer($io);

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
     */
    private function buildServer(ConsoleIo $io): Server
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
            ->setProtocolVersion($protocolVersion)
            ->setDiscovery(
                basePath: ROOT,
                scanDirs: $scanDirs,
                excludeDirs: $excludeDirs,
            );

        // Set container from DI
        $builder->setContainer($this->container);

        $io->out('<info>Discovering MCP elements...</info>');
        $server = $builder->build();

        $io->verbose('<success>Discovery complete</success>');

        return $server;
    }
}
