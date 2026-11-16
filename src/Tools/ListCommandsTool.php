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
use Synapse\Service\CommandService;

/**
 * List all available CakePHP console commands with optional filtering and sorting
 */
#[Name('list_commands')]
#[Description('List all available CakePHP console commands with optional filtering and sorting')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class ListCommandsTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'plugin' => $schema->string()
                ->description('Filter by plugin name')
                ->nullable(),
            'namespace' => $schema->string()
                ->description('Filter by command namespace')
                ->nullable(),
            'sort' => $schema->boolean()
                ->description('Sort commands alphabetically by name')
                ->default(false),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\CommandService $service Application service
     */
    public function handle(Request $request, CommandService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->listCommands(
            $arguments['plugin'],
            $arguments['namespace'],
            $arguments['sort'],
        ));
    }
}
