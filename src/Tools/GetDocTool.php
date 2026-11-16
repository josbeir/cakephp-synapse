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
 * Get full content of a documentation file by document ID
 */
#[Name('get_doc')]
#[Description('Get full content of a documentation file by document ID')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class GetDocTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'docId' => $schema->string()
                ->description("Document ID in format 'source::path' (e.g., 'cakephp-6x::docs/en/controllers.md')")
                ->required(),
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

        return $this->respond(fn(): mixed => $service->getDocument(
            $arguments['docId'],
        ));
    }
}
