<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\SystemService;

/**
 * Get the application debug status and environment.
 */
#[Name('debug_status')]
#[Description('Get the application debug status and environment.')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class DebugStatusTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\SystemService $service Application service
     */
    public function handle(SystemService $service): Response
    {
        return $this->respond($service->getDebugStatus(...));
    }
}
