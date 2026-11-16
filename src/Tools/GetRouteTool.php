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
use Synapse\Service\RouteService;

/**
 * Get detailed information about a specific named route
 */
#[Name('get_route')]
#[Description('Get detailed information about a specific named route')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class GetRouteTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The route name to look up')
                ->required(),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\RouteService $service Application service
     */
    public function handle(Request $request, RouteService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->getRoute(
            $arguments['name'],
        ));
    }
}
