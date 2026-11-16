<?php
declare(strict_types=1);

namespace Synapse\Resources;

use Cake\Log\Log;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\MimeType;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Contracts\HasUriTemplate;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Support\UriTemplate;
use Synapse\Exception\CapabilityException;
use Synapse\Service\DocumentationResourceService;
use Throwable;

/**
 * Get full content of a documentation file by ID.
 */
#[Name('documentation_content')]
#[Description('Get full content of a documentation file by ID')]
#[MimeType('text/markdown')]
class DocumentationContentResource extends Resource implements HasUriTemplate
{
    /**
     * @inheritDoc
     */
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('docs://content/{docId}');
    }

    /**
     * Read indexed documentation using URI template variables.
     *
     * @param \Crustum\Mcp\Request $request Resource request
     * @param \Synapse\Service\DocumentationResourceService $service Documentation service
     */
    public function handle(Request $request, DocumentationResourceService $service): Response
    {
        try {
            return Response::text($service->contentResource($request->get('docId')));
        } catch (CapabilityException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Synapse resource failed: ' . $exception->getMessage());

            return Response::error('The documentation could not be read. Check the application logs for details.');
        }
    }
}
