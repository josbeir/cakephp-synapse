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
 * Get application, CakePHP, PHP, and runtime information.
 */
#[Name('system_info')]
#[Description('Get application, CakePHP, PHP, and runtime information.')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class SystemInfoTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\SystemService $service Application service
     */
    public function handle(SystemService $service): Response
    {
        return $this->respond($service->getSystemInfo(...));
    }
}
