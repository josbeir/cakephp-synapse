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
use Synapse\Service\LogService;

/**
 * Read the last N lines from a log file, optionally filtered by log level. Use log_list to discover available
 * files.
 */
#[Name('log_read')]
#[Description(
    'Read the last N lines from a log file, optionally filtered by log level. Use log_list to ' .
    'discover available files.',
)]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class LogReadTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()
                ->description("Log file name (relative to LOGS directory, e.g. 'app.log')")
                ->required(),
            'lines' => $schema->integer()
                ->description('Maximum number of lines to return from the end of the file (tail)')
                ->default(100),
            'level' => $schema->string()
                ->description('Filter lines containing this string (case-insensitive). Pass null to return all.')
                ->nullable(),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\LogService $service Application service
     */
    public function handle(Request $request, LogService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->readLog(
            $arguments['file'],
            $arguments['lines'],
            $arguments['level'],
        ));
    }
}
