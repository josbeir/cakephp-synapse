<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\CacheService;

/**
 * List all configured CakePHP cache configurations with engine information
 */
#[Name('cache_configs')]
#[Description('List all configured CakePHP cache configurations with engine information')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class CacheConfigsTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\CacheService $service Application service
     */
    public function handle(CacheService $service): Response
    {
        return $this->respond($service->listConfigs(...));
    }
}
