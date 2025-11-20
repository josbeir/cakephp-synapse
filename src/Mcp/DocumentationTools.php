<?php
declare(strict_types=1);

namespace Synapse\Mcp;

use Exception;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextResourceContents;
use Synapse\Documentation\DocumentSearchService;

/**
 * Documentation Tools
 *
 * MCP tools and resources for searching CakePHP documentation.
 */
class DocumentationTools
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
     * @param string $query The search query
     * @param int $limit Maximum number of results to return (default: 10, max: 50)
     * @param bool $fuzzy Enable fuzzy/prefix matching for typo tolerance (default: false)
     * @param array<string>|null $sources Filter results to specific documentation sources (e.g., ['cakephp-5x'])
     * @return array{results: array<int, array<string, mixed>>, total: int, query: string, options: array<string, mixed>} Search results with metadata
     */
    #[McpTool(
        name: 'search_docs',
        description: 'Search CakePHP documentation using full-text search with relevance ranking',
    )]
    public function searchDocs(
        string $query,
        int $limit = 10,
        bool $fuzzy = false,
        ?array $sources = null,
    ): array {
        try {
            // Validate and sanitize inputs
            if (trim($query) === '') {
                throw new ToolCallException('Search query cannot be empty');
            }

            $limit = max(1, min(50, $limit));

            $options = [
                'limit' => $limit,
                'highlight' => true,
            ];

            if ($sources !== null && $sources !== []) {
                $options['sources'] = $sources;
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
                    'sources' => $sources ?? 'all',
                ],
            ];
        } catch (Exception $exception) {
            $message = sprintf('Failed to search documentation: %s', $exception->getMessage());
            throw new ToolCallException($message);
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
    #[McpTool(
        name: 'docs_stats',
        description: 'Get statistics about the indexed documentation',
    )]
    public function getStats(): array
    {
        try {
            return $this->searchService->getStatistics();
        } catch (Exception $exception) {
            $message = sprintf('Failed to get documentation statistics: %s', $exception->getMessage());
            throw new ToolCallException($message);
        }
    }

    /**
     * Documentation search resource template.
     *
     * Provides access to documentation search results as a resource.
     * The URI template allows querying documentation by search term:
     * docs://search/{query}
     *
     * Example URIs:
     * - docs://search/authentication
     * - docs://search/database queries
     * - docs://search/caching
     *
     * @param string $query The search query from the URI
     * @return array{contents: array<\Mcp\Schema\Content\TextResourceContents>} Resource contents
     */
    #[McpResourceTemplate(
        uriTemplate: 'docs://search/{query}',
        name: 'Documentation Search',
        description: 'Search CakePHP documentation and return formatted results',
        mimeType: 'text/markdown',
    )]
    public function searchResource(string $query): array
    {
        try {
            if (trim($query) === '') {
                throw new ToolCallException('Search query cannot be empty');
            }

            // Search with default options
            $results = $this->searchService->search($query, [
                'limit' => 10,
                'highlight' => true,
            ]);

            // Format results as markdown
            $content = $this->formatResultsAsMarkdown($query, $results);

            $uri = sprintf('docs://search/%s', urlencode($query));

            return [
                'contents' => [
                    new TextResourceContents(
                        uri: $uri,
                        mimeType: 'text/markdown',
                        text: $content,
                    ),
                ],
            ];
        } catch (Exception $exception) {
            $message = sprintf('Failed to read documentation resource: %s', $exception->getMessage());
            throw new ToolCallException($message);
        }
    }

    /**
     * Format search results as markdown
     *
     * @param string $query The search query
     * @param array<array<string, mixed>> $results Search results
     * @return string Formatted markdown content
     */
    private function formatResultsAsMarkdown(string $query, array $results): string
    {
        $markdown = sprintf("# Documentation Search: %s\n\n", $query);

        if ($results === []) {
            return $markdown . "No results found.\n";
        }

        $markdown .= sprintf("Found %d result(s):\n\n", count($results));

        foreach ($results as $i => $result) {
            $rank = $i + 1;
            $title = $result['title'] ?? 'Untitled';
            $path = $result['path'] ?? '';
            $source = $result['source'] ?? '';
            $snippet = $result['snippet'] ?? '';
            $rank_score = $result['rank'] ?? 0;

            $markdown .= sprintf("## %d. %s\n\n", $rank, $title);
            $markdown .= sprintf("**Source:** %s  \n", $source);
            $markdown .= sprintf("**Path:** `%s`  \n", $path);
            $markdown .= sprintf("**Relevance:** %.2f  \n\n", $rank_score);

            if ($snippet !== '') {
                $markdown .= "**Snippet:**\n\n";
                $markdown .= '> ' . str_replace("\n", "\n> ", $snippet) . "\n\n";
            }

            $markdown .= "---\n\n";
        }

        return $markdown;
    }
}
