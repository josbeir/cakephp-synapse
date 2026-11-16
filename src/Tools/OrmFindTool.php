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
use Synapse\Service\ModelService;

/**
 * Run a find query on a CakePHP ORM Table. Type must be 'all', 'first', or 'count'.
 */
#[Name('orm_find')]
#[Description("Run a find query on a CakePHP ORM Table. Type must be 'all', 'first', or 'count'.")]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class OrmFindTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'alias' => $schema->string()
                ->description("Table alias in CamelCase (e.g. 'Articles')")
                ->required(),
            'connection' => $schema->string()
                ->description("Connection name to use (defaults to 'default')")
                ->default('default'),
            'type' => $schema->string()
                ->description("Find type: 'all', 'first', or 'count'")
                ->default('all')
                ->enum(['all', 'first', 'count']),
            'limit' => $schema->integer()
                ->description('Maximum number of records to return (clamped 1–100)')
                ->default(10),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\ModelService $service Application service
     */
    public function handle(Request $request, ModelService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->ormFind(
            $arguments['alias'],
            $arguments['connection'],
            $arguments['type'],
            $arguments['limit'],
        ));
    }
}
