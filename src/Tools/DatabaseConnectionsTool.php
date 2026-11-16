<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\DatabaseService;

/**
 * Inspect available database connections, including the default connection
 */
#[Name('database_connections')]
#[Description('Inspect available database connections, including the default connection')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class DatabaseConnectionsTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\DatabaseService $service Application service
     */
    public function handle(DatabaseService $service): Response
    {
        return $this->respond($service->listConnections(...));
    }
}
