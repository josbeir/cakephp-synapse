<?php
declare(strict_types=1);

namespace Synapse\Resources;

use Exception;
use Mcp\Capability\Attribute\McpResourceTemplate;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextResourceContents;
use Synapse\Documentation\DocumentSearchService;

/**
 * Documentation Resources
 *
 * MCP resources for accessing CakePHP documentation content.
 */
class DocumentationResources
{
    /**
     * Constructor
     */
    public function __construct(
        private DocumentSearchService $searchService = new DocumentSearchService(),
    ) {
    }

    /**
     * Documentation search resource template.
     *
     * Provides a resource interface for searching documentation.
     * The URI template allows searching via URI pattern:
     * docs://search/{query}
     *
     * Returns formatted search results as markdown.
     *
     * Example URIs:
     * - docs://search/authentication
     * - docs://search/database queries
     * - docs://search/caching
     *
     * @param string $query The search query from the URI
     * @return \Mcp\Schema\Content\TextResourceContents Resource contents
     */
    #[McpResourceTemplate(
        uriTemplate: 'docs://search/{query}',
        name: 'documentation_search',
        description: 'Search CakePHP documentation and return formatted results',
        mimeType: 'text/markdown',
    )]
    public function searchResource(string $query): TextResourceContents
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

            return new TextResourceContents(
                uri: $uri,
                mimeType: 'text/markdown',
                text: $content,
            );
        } catch (Exception $exception) {
            $message = sprintf('Failed to read documentation resource: %s', $exception->getMessage());
            throw new ToolCallException($message);
        }
    }

    /**
     * Documentation content resource template.
     *
     * Provides direct access to documentation files as resources.
     * The URI template allows retrieving full document content by ID:
     * docs://content/{docId}
     *
     * Returns original markdown with full formatting preserved.
     *
     * Example URIs:
     * - docs://content/cakephp-5x::docs/en/controllers.md
     * - docs://content/cakephp-5x::docs/en/orm/query-builder.md
     *
     * @param string $docId Document ID in format 'source::path'
     * @return \Mcp\Schema\Content\TextResourceContents Resource contents
     */
    #[McpResourceTemplate(
        uriTemplate: 'docs://content/{docId}',
        name: 'documentation_content',
        description: 'Get full content of a documentation file by ID',
        mimeType: 'text/markdown',
    )]
    public function contentResource(string $docId): TextResourceContents
    {
        try {
            if (trim($docId) === '') {
                throw new ToolCallException('Document ID cannot be empty');
            }

            // Get document from search engine database (returns original markdown content)
            $searchEngine = $this->searchService->getSearchEngine();
            $document = $searchEngine->getDocumentById($docId);

            if ($document === null) {
                throw new ToolCallException(sprintf('Document not found: %s', $docId));
            }

            $uri = sprintf('docs://content/%s', $docId);

            return new TextResourceContents(
                uri: $uri,
                mimeType: 'text/markdown',
                text: $document['content'],
            );
        } catch (ToolCallException $exception) {
            throw $exception;
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
            $relativePath = $result['path'] ?? '';

            $source = $result['source'] ?? '';
            $snippet = $result['snippet'] ?? '';
            $rank_score = $result['score'] ?? 0;

            $markdown .= sprintf("## %d. %s\n\n", $rank, $title);
            $markdown .= sprintf("**Source:** %s  \n", $source);

            if ($relativePath !== '') {
                $markdown .= sprintf("**Path:** `%s`  \n", $relativePath);
            }

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
