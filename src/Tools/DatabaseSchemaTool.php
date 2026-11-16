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
use Synapse\Service\DatabaseService;

/**
 * Read database schema for tables, columns, constraints, and indexes
 */
#[Name('database_schema')]
#[Description('Read database schema for tables, columns, constraints, and indexes')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class DatabaseSchemaTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'connection' => $schema->string()
                ->description("Connection name (defaults to 'default')")
                ->default('default'),
            'table' => $schema->string()
                ->description('Specific table name (optional, if omitted returns all tables)')
                ->nullable(),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\DatabaseService $service Application service
     */
    public function handle(Request $request, DatabaseService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->describeSchema(
            $arguments['connection'],
            $arguments['table'],
        ));
    }
}
