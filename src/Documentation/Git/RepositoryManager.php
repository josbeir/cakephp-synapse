<?php
declare(strict_types=1);

namespace Synapse\Documentation\Git;

use Cake\Core\Configure;
use RuntimeException;

/**
 * Manages multiple documentation repositories
 *
 * Handles initialization and cloning of configured documentation sources.
 */
class RepositoryManager
{
    /**
     * @var array<string, \Synapse\Documentation\Git\Repository>
     */
    private array $repositories = [];

    /**
     * Constructor
     *
     * @param string $cacheDir Base cache directory
     * @param array<string, array<string, mixed>> $sources Source configurations
     */
    public function __construct(
        private string $cacheDir = '',
        private array $sources = [],
    ) {
        if ($this->cacheDir === '') {
            $defaultCache = TMP . 'synapse' . DS . 'docs';
            $this->cacheDir = (string)Configure::read(
                'Synapse.documentation.cache_dir',
                $defaultCache,
            );
        }

        if ($this->sources === []) {
            $this->sources = (array)Configure::read('Synapse.documentation.sources', []);
        }
    }

    /**
     * Get a repository instance by source key
     *
     * @param string $sourceKey Source configuration key
     * @throws \RuntimeException If source is not configured or disabled
     */
    public function getRepository(string $sourceKey): Repository
    {
        if (isset($this->repositories[$sourceKey])) {
            return $this->repositories[$sourceKey];
        }

        $config = $this->getSourceConfig($sourceKey);

        $repository = new Repository(
            url: $config['repository'],
            branch: $config['branch'],
            path: $this->cacheDir . DS . $sourceKey,
            root: $config['root'] ?? '',
        );

        $this->repositories[$sourceKey] = $repository;

        return $repository;
    }

    /**
     * Get all enabled repositories
     *
     * @return array<string, \Synapse\Documentation\Git\Repository>
     */
    public function getAllRepositories(): array
    {
        $repositories = [];

        foreach ($this->sources as $key => $config) {
            if (!($config['enabled'] ?? false)) {
                continue;
            }

            $repositories[$key] = $this->getRepository($key);
        }

        return $repositories;
    }

    /**
     * Initialize a repository (clone if needed)
     *
     * @param string $sourceKey Source configuration key
     * @return bool Whether repository was cloned (false if already exists)
     * @throws \RuntimeException If clone fails
     */
    public function initialize(string $sourceKey): bool
    {
        $repository = $this->getRepository($sourceKey);

        if ($repository->exists()) {
            return false;
        }

        $repository->clone();

        return true;
    }

    /**
     * Initialize all enabled repositories
     *
     * @return array<string, bool> Map of source key => whether it was cloned
     */
    public function initializeAll(): array
    {
        $results = [];

        foreach (array_keys($this->getAllRepositories()) as $key) {
            try {
                $results[$key] = $this->initialize($key);
            } catch (RuntimeException $e) {
                $results[$key] = false;
            }
        }

        return $results;
    }

    /**
     * Check if a repository exists locally
     *
     * @param string $sourceKey Source configuration key
     */
    public function exists(string $sourceKey): bool
    {
        try {
            $repository = $this->getRepository($sourceKey);

            return $repository->exists();
        } catch (RuntimeException $runtimeException) {
            return false;
        }
    }

    /**
     * Get list of enabled source keys
     *
     * @return array<string>
     */
    public function getEnabledSources(): array
    {
        $sources = [];

        foreach ($this->sources as $key => $config) {
            if ($config['enabled'] ?? false) {
                $sources[] = $key;
            }
        }

        return $sources;
    }

    /**
     * Get source configuration
     *
     * @param string $sourceKey Source configuration key
     * @return array<string, mixed>
     * @throws \RuntimeException If source is not configured or disabled
     */
    public function getSourceConfig(string $sourceKey): array
    {
        if (!isset($this->sources[$sourceKey])) {
            throw new RuntimeException(sprintf(
                'Documentation source "%s" is not configured',
                $sourceKey,
            ));
        }

        $config = $this->sources[$sourceKey];

        if (!($config['enabled'] ?? false)) {
            throw new RuntimeException(sprintf(
                'Documentation source "%s" is disabled',
                $sourceKey,
            ));
        }

        // Validate required fields
        $required = ['repository', 'branch'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new RuntimeException(sprintf(
                    'Documentation source "%s" is missing required field: %s',
                    $sourceKey,
                    $field,
                ));
            }
        }

        return $config;
    }

    /**
     * Get metadata for a source
     *
     * @param string $sourceKey Source configuration key
     * @return array<string, mixed>
     */
    public function getMetadata(string $sourceKey): array
    {
        $config = $this->getSourceConfig($sourceKey);

        return $config['metadata'] ?? [];
    }

    /**
     * Get cache directory
     */
    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Get repository path for a source
     *
     * @param string $sourceKey Source configuration key
     */
    public function getRepositoryPath(string $sourceKey): string
    {
        return $this->cacheDir . DS . $sourceKey;
    }
}
