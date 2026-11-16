<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\LogService;

/**
 * List all available log files in the application logs directory. Returns an empty array when no .log files
 * exist or when the application uses non-filesystem logging (database, syslog, etc.).
 */
#[Name('log_list')]
#[Description(
    'List all available log files in the application logs directory. Returns an empty array when no ' .
    '.log files exist or when the application uses non-filesystem logging (database, syslog, etc.).',
)]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class LogListTool extends AbstractTool
{
    /**
     * Invoke the capability using application services.
     *
     * @param \Synapse\Service\LogService $service Application service
     */
    public function handle(LogService $service): Response
    {
        return $this->respond($service->listLogs(...));
    }
}
