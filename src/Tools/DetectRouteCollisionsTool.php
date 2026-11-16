<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\RouteService;

/**
 * Detect potential route collisions and conflicts
 */
#[Name('detect_route_collisions')]
#[Description('Detect potential route collisions and conflicts')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class DetectRouteCollisionsTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\RouteService $service Application service
     */
    public function handle(RouteService $service): Response
    {
        return $this->respond($service->detectRouteCollisions(...));
    }
}
