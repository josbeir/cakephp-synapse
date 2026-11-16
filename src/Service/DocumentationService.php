<?php
declare(strict_types=1);

namespace Synapse\Service;

use Exception;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Exception\CapabilityException;

/**
 * Documentation Tools
 *
 * MCP tools for searching CakePHP documentation.
 */
class DocumentationService
{
    private DocumentSearchService $searchService;

    /**
     * Constructor
     *
     * @param \Synapse\Documentation\DocumentSearchService|null $searchService Search service instance
     */
    public function __construct(?DocumentSearchService $searchService = null)
    {
        $this->searchService = $searchService ?? new DocumentSearchService();
    }

    /**
     * Search CakePHP documentation.
     *
     * Performs full-text search across indexed CakePHP documentation with
     * BM25 ranking and optional highlighting. Returns relevant documentation
     * snippets with context.
     *
     * Results are sorted by relevance (best matches first). Each result includes
     * a 'score' field - higher scores indicate better relevance.
     *
     * @param string $query The search query
     * @param int $limit Maximum number of results to return (default: 10, max: 50)
     * @param bool $fuzzy Enable fuzzy/prefix matching for typo tolerance (default: false)
     * @param string $sources Comma-separated list of sources to filter (e.g., 'cakephp-6x,cakephp-5x')
     * @return array{results: array<int, array<string, mixed>>, total: int, query: string, options: array<string, mixed>} Search results with metadata
     */
    public function searchDocs(
        string $query,
        int $limit = 10,
        bool $fuzzy = false,
        string $sources = '',
    ): array {
        try {
            // Validate and sanitize inputs
            if (trim($query) === '') {
                throw new CapabilityException('Search query cannot be empty');
            }

            $limit = max(1, min(50, $limit));

            $options = [
                'limit' => $limit,
                'highlight' => true,
            ];

            // Parse comma-separated sources
            if ($sources !== '') {
                $sourcesArray = array_map('trim', explode(',', $sources));
                $sourcesArray = array_filter($sourcesArray);
                if ($sourcesArray !== []) {
                    $options['sources'] = $sourcesArray;
                }
            }

            if ($fuzzy) {
                $options['fuzzy'] = true;
            }

            $results = $this->searchService->search($query, $options);

            return [
                'results' => $results,
                'total' => count($results),
                'query' => $query,
                'options' => [
                    'limit' => $limit,
                    'fuzzy' => $fuzzy,
                    'sources' => $sources !== '' ? $sources : 'all',
                ],
            ];
        } catch (Exception $exception) {
            $message = sprintf('Failed to search documentation: %s', $exception->getMessage());
            throw new CapabilityException($message, $exception->getCode(), $exception);
        }
    }

    /**
     * Get documentation search index statistics.
     *
     * Returns information about the indexed documentation including
     * total document count, documents per source, and available sources.
     *
     * @return array<string, mixed> Index statistics
     */
    public function getStats(): array
    {
        try {
            return $this->searchService->getStatistics();
        } catch (Exception $exception) {
            $message = sprintf('Failed to get documentation statistics: %s', $exception->getMessage());
            throw new CapabilityException($message, $exception->getCode(), $exception);
        }
    }

    /**
     * Get full documentation content by document ID.
     *
     * Retrieves the complete markdown content of a documentation file.
     * Use this after searching to read the full document.
     *
     * Returns original markdown with full formatting (code blocks, links, lists, etc.)
     * not the cleaned content used for search indexing.
     *
     * @param string $docId Document ID in format 'source::path' (e.g., 'cakephp-6x::docs/en/controllers.md')
     * @return array{content: string, title: string, source: string, path: string, id: string, metadata: array<string, mixed>} Document content and metadata
     */
    public function getDocument(string $docId): array
    {
        try {
            // Validate input
            if (trim($docId) === '') {
                throw new CapabilityException('Document ID cannot be empty');
            }

            // Get document from search engine database (returns original markdown content)
            $searchEngine = $this->searchService->getSearchEngine();
            $document = $searchEngine->getDocumentById($docId);

            if ($document === null) {
                throw new CapabilityException(sprintf('Document not found: %s', $docId));
            }

            return [
                'content' => $document['content'],
                'title' => $document['title'],
                'source' => $document['source'],
                'path' => $document['path'],
                'id' => $document['id'],
                'metadata' => $document['metadata'],
            ];
        } catch (CapabilityException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            $message = sprintf('Failed to get document: %s', $exception->getMessage());
            throw new CapabilityException($message, $exception->getCode(), $exception);
        }
    }
}
