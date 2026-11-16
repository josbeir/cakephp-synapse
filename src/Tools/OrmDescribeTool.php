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
 * Describe a CakePHP ORM Table: associations, behaviors, display field, primary key, entity class
 */
#[Name('orm_describe')]
#[Description(
    'Describe a CakePHP ORM Table: associations, behaviors, display field, primary key, entity ' .
    'class',
)]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class OrmDescribeTool extends AbstractTool
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

        return $this->respond(fn(): mixed => $service->ormDescribe(
            $arguments['alias'],
            $arguments['connection'],
        ));
    }
}
