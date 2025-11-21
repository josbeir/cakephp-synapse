<?php
declare(strict_types=1);

namespace Synapse\Documentation;

use Cake\Core\Configure;
use RuntimeException;
use Synapse\Documentation\Git\RepositoryManager;

/**
 * Document Search Service
 *
 * Coordinates document indexing and searching across multiple documentation sources.
 */
class DocumentSearchService
{
    private SearchEngine $searchEngine;

    private RepositoryManager $repositoryManager;

    private DocumentProcessor $documentProcessor;

    /**
     * Constructor
     *
     * @param string|null $databasePath Path to search database (null = use config)
     * @param \Synapse\Documentation\Git\RepositoryManager|null $repositoryManager Repository manager
     * @param \Synapse\Documentation\DocumentProcessor|null $documentProcessor Document processor
     */
    public function __construct(
        ?string $databasePath = null,
        ?RepositoryManager $repositoryManager = null,
        ?DocumentProcessor $documentProcessor = null,
    ) {
        $cacheDir = Configure::read('Synapse.documentation.cache_dir', TMP . 'synapse' . DS . 'docs');
        $databasePath = $databasePath ?? Configure::read(
            'Synapse.documentation.search_db',
            $cacheDir . DS . 'search.db',
        );

        // Ensure cache directory exists
        $dir = dirname($databasePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
        }

        $this->searchEngine = new SearchEngine($databasePath);
        $this->repositoryManager = $repositoryManager ?? new RepositoryManager();
        $this->documentProcessor = $documentProcessor ?? new DocumentProcessor();
    }

    /**
     * Index all enabled documentation sources
     *
     * @param bool $force Force re-index even if repository exists
     * @param bool $pull Pull latest changes before indexing
     * @return array<string, int> Map of source key => number of documents indexed
     */
    public function indexAll(bool $force = false, bool $pull = false): array
    {
        $results = [];

        foreach ($this->repositoryManager->getEnabledSources() as $sourceKey) {
            $results[$sourceKey] = $this->indexSource($sourceKey, $force, $pull);
        }

        return $results;
    }

    /**
     * Index a single documentation source
     *
     * @param string $sourceKey Source configuration key
     * @param bool $force Force re-index even if repository exists
     * @param bool $pull Pull latest changes before indexing
     * @return int Number of documents indexed
     * @throws \RuntimeException If source cannot be indexed
     */
    public function indexSource(string $sourceKey, bool $force = false, bool $pull = false): int
    {
        $repository = $this->repositoryManager->getRepository($sourceKey);

        // Clone repository if needed
        if (!$repository->exists()) {
            $repository->clone();
        } elseif ($pull) {
            // Pull latest changes if requested
            $repository->pull();
        }

        // Clear existing documents for this source if forcing re-index
        if ($force) {
            $this->searchEngine->clearSource($sourceKey);
        }

        // Get all markdown files from repository
        $files = $repository->getMarkdownFiles();

        if ($files === []) {
            return 0;
        }

        // Process documents in batches
        $batchSize = Configure::read('Synapse.documentation.search.batch_size', 100);
        $batches = array_chunk($files, $batchSize);
        $totalIndexed = 0;

        foreach ($batches as $batch) {
            $documents = $this->documentProcessor->processBatch($repository, $batch, $sourceKey);

            if ($documents !== []) {
                $this->searchEngine->indexBatch($documents);
                $totalIndexed += count($documents);
            }
        }

        return $totalIndexed;
    }

    /**
     * Search across documentation
     *
     * @param string $query Search query
     * @param array<string, mixed> $options Search options
     * @return array<array<string, mixed>> Search results
     */
    public function search(string $query, array $options = []): array
    {
        // Auto-build index if it doesn't exist and auto_build is enabled
        if ($this->shouldAutoBuild()) {
            $this->indexAll();
        }

        $defaultLimit = Configure::read('Synapse.documentation.search.default_limit', 10);
        $highlightEnabled = Configure::read('Synapse.documentation.search.highlight', true);

        $searchOptions = [
            'limit' => $options['limit'] ?? $defaultLimit,
            'sources' => $options['sources'] ?? [],
            'highlight' => $options['highlight'] ?? $highlightEnabled,
        ];

        return $this->searchEngine->search($query, $searchOptions);
    }

    /**
     * Get statistics about the search index
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'total_documents' => $this->searchEngine->getDocumentCount(),
            'documents_by_source' => $this->searchEngine->getDocumentCountBySource(),
            'sources' => $this->repositoryManager->getEnabledSources(),
        ];
    }

    /**
     * Clear all indexed documents
     */
    public function clearIndex(): void
    {
        $this->searchEngine->clear();
    }

    /**
     * Clear indexed documents for a specific source
     *
     * @param string $sourceKey Source configuration key
     */
    public function clearSource(string $sourceKey): void
    {
        $this->searchEngine->clearSource($sourceKey);
    }

    /**
     * Optimize the search index
     */
    public function optimize(): void
    {
        $this->searchEngine->optimize();
    }

    /**
     * Destroy the search index
     *
     * Completely removes the search database file.
     * This is a destructive operation that cannot be undone.
     *
     * @return bool True if the index was destroyed, false if it didn't exist
     */
    public function destroy(): bool
    {
        return $this->searchEngine->destroy();
    }

    /**
     * Check if repository exists for a source
     *
     * @param string $sourceKey Source configuration key
     */
    public function hasRepository(string $sourceKey): bool
    {
        return $this->repositoryManager->exists($sourceKey);
    }

    /**
     * Initialize all repositories (clone if needed)
     *
     * @return array<string, bool> Map of source key => whether it was cloned
     */
    public function initializeRepositories(): array
    {
        return $this->repositoryManager->initializeAll();
    }

    /**
     * Get the search engine instance
     */
    public function getSearchEngine(): SearchEngine
    {
        return $this->searchEngine;
    }

    /**
     * Get the repository manager instance
     */
    public function getRepositoryManager(): RepositoryManager
    {
        return $this->repositoryManager;
    }

    /**
     * Check if auto-build should happen
     */
    private function shouldAutoBuild(): bool
    {
        $autoBuild = Configure::read('Synapse.documentation.auto_build', true);

        if (!$autoBuild) {
            return false;
        }

        // Check if index is empty
        return $this->searchEngine->getDocumentCount() === 0;
    }
}
