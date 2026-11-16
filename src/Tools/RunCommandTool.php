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
 * Run a registered CakePHP console command. Use list_commands to discover available commands. Only commands
 * registered in the application command collection can be executed.
 */
#[Name('run_command')]
#[Description(
    'Run a registered CakePHP console command. Use list_commands to discover available commands. ' .
    'Only commands registered in the application command collection can be executed.',
)]
#[IsReadOnly(false)]
#[IsDestructive(true)]
#[IsIdempotent(false)]
class RunCommandTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description("Command name as registered (e.g. 'synapse search_docs')")
                ->required(),
            'args' => $schema->string()
                ->description("Additional arguments/flags passed verbatim (e.g. '--help')")
                ->default(''),
            'timeout' => $schema->integer()
                ->description('Maximum execution time in seconds (default: 60, max: 300)')
                ->default(60),
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

        return $this->respond(fn(): mixed => $service->runCommand(
            $arguments['name'],
            $arguments['args'],
            $arguments['timeout'],
        ));
    }
}
