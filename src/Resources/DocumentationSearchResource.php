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
 * Search CakePHP documentation and return formatted results.
 */
#[Name('documentation_search')]
#[Description('Search CakePHP documentation and return formatted results')]
#[MimeType('text/markdown')]
class DocumentationSearchResource extends Resource implements HasUriTemplate
{
    /**
     * @inheritDoc
     */
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('docs://search/{query}');
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
            return Response::text($service->searchResource($request->get('query')));
        } catch (CapabilityException $exception) {
            return Response::error($exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Synapse resource failed: ' . $exception->getMessage());

            return Response::error('The documentation could not be read. Check the application logs for details.');
        }
    }
}
