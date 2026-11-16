<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\DocumentationService;

/**
 * Search CakePHP documentation using full-text search with relevance ranking. Results are sorted by relevance
 * (best first) with scores where higher = more relevant.
 */
#[Name('search_docs')]
#[Description(
    'Search CakePHP documentation using full-text search with relevance ranking. Results are sorted ' .
    'by relevance (best first) with scores where higher = more relevant.',
)]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SearchDocsTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The search query')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of results to return (default: 10, max: 50)')
                ->default(10),
            'fuzzy' => $schema->boolean()
                ->description('Enable fuzzy/prefix matching for typo tolerance (default: false)')
                ->default(false),
            'sources' => $schema->string()
                ->description("Comma-separated list of sources to filter (e.g., 'cakephp-6x,cakephp-5x')")
                ->default(''),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\DocumentationService $service Application service
     */
    public function handle(Request $request, DocumentationService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->searchDocs(
            $arguments['query'],
            $arguments['limit'],
            $arguments['fuzzy'],
            $arguments['sources'],
        ));
    }
}
