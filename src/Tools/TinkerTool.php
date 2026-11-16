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
use Synapse\Service\TinkerService;

/**
 * Execute PHP code in the CakePHP application context. Use for debugging, testing code snippets, and exploring
 * the application. DO NOT create/modify data without explicit user approval. Prefer feature tests and existing
 * commands over custom code. Use $this->fetchTable() and $this->log() for ORM and logging access.
 */
#[Name('tinker')]
#[Description(
    'Execute PHP code in the CakePHP application context. Use for debugging, testing code snippets, ' .
    'and exploring the application. DO NOT create/modify data without explicit user approval. ' .
    'Prefer feature tests and existing commands over custom code. Use $this->fetchTable() and ' .
    '$this->log() for ORM and logging access.',
)]
#[IsReadOnly(false)]
#[IsDestructive(true)]
#[IsIdempotent(false)]
class TinkerTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()
                ->description('PHP code to execute (without opening <?php tags)')
                ->required(),
            'timeout' => $schema->integer()
                ->description('Maximum execution time in seconds (default: 30, max: 180)')
                ->default(30),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\TinkerService $service Application service
     */
    public function handle(Request $request, TinkerService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->execute(
            $arguments['code'],
            $arguments['timeout'],
        ));
    }
}
