<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\DocumentationService;

/**
 * Get statistics about the indexed documentation
 */
#[Name('docs_stats')]
#[Description('Get statistics about the indexed documentation')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class DocsStatsTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\DocumentationService $service Application service
     */
    public function handle(DocumentationService $service): Response
    {
        return $this->respond($service->getStats(...));
    }
}
