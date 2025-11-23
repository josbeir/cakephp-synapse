<?php
declare(strict_types=1);

namespace Synapse\Builder;

use Cake\Cache\Cache;
use Cake\Core\ContainerInterface;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * ServerBuilder
 *
 * Responsible for constructing and configuring MCP Server instances.
 * Extracted from ServerCommand for better testability.
 */
class ServerBuilder
{
    /**
     * Default cache engine name for discovery caching
     */
    public const DEFAULT_CACHE_ENGINE = 'default';

    /**
     * @var array<string, string>
     */
    private array $serverInfo;

    /**
     * @var array<string>
     */
    private array $scanDirs;

    /**
     * @var array<string>
     */
    private array $excludeDirs;

    private string $protocolVersion;

    private string $basePath;

    private ?string $cacheEngine;

    private ?ContainerInterface $container = null;

    private ?LoggerInterface $logger = null;

    /**
     * Constructor
     *
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct(array $config = [])
    {
        // Set defaults from config or use sensible defaults
        $this->serverInfo = $config['serverInfo'] ?? [
            'name' => 'Adaptic MCP Server',
            'version' => '1.0.0',
        ];

        $discovery = $config['discovery'] ?? [];
        $this->scanDirs = $discovery['scanDirs'] ?? ['src'];
        $this->excludeDirs = $discovery['excludeDirs'] ?? ['tests', 'vendor', 'tmp'];
        $this->cacheEngine = $discovery['cache'] ?? self::DEFAULT_CACHE_ENGINE;

        $this->protocolVersion = $config['protocolVersion'] ?? '2024-11-05';
        $this->basePath = $config['basePath'] ?? ROOT;
    }

    /**
     * Set the DI container
     *
     * @param \Cake\Core\ContainerInterface|null $container DI container
     */
    public function setContainer(?ContainerInterface $container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set PSR-3 logger for MCP server
     *
     * @param \Psr\Log\LoggerInterface|null $logger PSR-3 logger instance
     */
    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get configured logger
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Add plugin built-in tools and prompts directories to scan dirs
     *
     * @return $this
     */
    public function withPluginTools()
    {
        $scanDirs = [
            'Tools',
            'Prompts',
            'Resources',
        ];

        $pluginSrcPath = dirname(__DIR__);
        $pluginSrcPath = str_replace(ROOT, '', $pluginSrcPath);
        $pluginSrcPath = ltrim($pluginSrcPath, DIRECTORY_SEPARATOR);

        foreach ($scanDirs as $dir) {
            $path = $pluginSrcPath . DIRECTORY_SEPARATOR . $dir;
            if (!in_array($path, $this->scanDirs, true)) {
                $this->scanDirs[] = $path;
            }
        }

        return $this;
    }

    /**
     * Add a scan directory
     *
     * @param string $path Directory path to scan
     * @return $this
     */
    public function addScanDirectory(string $path)
    {
        if (!in_array($path, $this->scanDirs, true)) {
            $this->scanDirs[] = $path;
        }

        return $this;
    }

    /**
     * Set protocol version
     *
     * @param string $version Protocol version string
     * @return $this
     */
    public function setProtocolVersion(string $version)
    {
        $this->protocolVersion = $version;

        return $this;
    }

    /**
     * Disable caching
     *
     * @return $this
     */
    public function withoutCache()
    {
        $this->cacheEngine = null;

        return $this;
    }

    /**
     * Build the MCP Server instance
     */
    public function build(): Server
    {
        $protocolVersion = ProtocolVersion::from($this->protocolVersion);

        $builder = Server::builder()
            ->setServerInfo($this->serverInfo['name'], $this->serverInfo['version'])
            ->setProtocolVersion($protocolVersion);

        // Set logger if provided
        if ($this->logger instanceof LoggerInterface) {
            $builder->setLogger($this->logger);
        }

        // Get cache if enabled
        $cache = null;
        if ($this->cacheEngine !== null) {
            $cache = $this->getCache($this->cacheEngine);
        }

        $builder->setDiscovery(
            basePath: $this->basePath,
            scanDirs: $this->scanDirs,
            excludeDirs: $this->excludeDirs,
            cache: $cache,
        );

        if ($this->container instanceof ContainerInterface) {
            $builder->setContainer($this->container);
        }

        return $builder->build();
    }

    /**
     * Get PSR-16 cache instance from CakePHP cache configuration
     *
     * @param string $engineName Cache engine name
     */
    public function getCache(string $engineName): ?CacheInterface
    {
        try {
            return Cache::pool($engineName);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Clear discovery cache
     *
     * @param string $engineName Cache engine name
     */
    public static function clearCache(string $engineName): bool
    {
        try {
            return Cache::clear($engineName);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get server info configuration
     *
     * @return array<string, string>
     */
    public function getServerInfo(): array
    {
        return $this->serverInfo;
    }

    /**
     * Get scan directories
     *
     * @return array<string>
     */
    public function getScanDirs(): array
    {
        return $this->scanDirs;
    }

    /**
     * Get exclude directories
     *
     * @return array<string>
     */
    public function getExcludeDirs(): array
    {
        return $this->excludeDirs;
    }

    /**
     * Get protocol version
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * Get cache engine name
     */
    public function getCacheEngine(): ?string
    {
        return $this->cacheEngine;
    }

    /**
     * Get base path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
