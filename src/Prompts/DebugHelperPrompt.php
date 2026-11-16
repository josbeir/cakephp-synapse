<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Debug Helper Prompt
 *
 * Systematic debugging workflow for CakePHP errors and issues.
 */
#[Name('debug-helper')]
#[Description('Systematic debugging workflow for CakePHP errors and issues')]
class DebugHelperPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('error', 'Error message, stack trace, or description of the issue', true),
            new Argument('context', 'Context area (controller, model, database, view)', false),
        ];
    }

    /**
     * Render the advertised prompt arguments.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @return array<\Crustum\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $arguments = $this->validatedArguments($request);

        return $this->render($arguments['error'], $arguments['context'] ?? '');
    }

    /**
     * Systematic debugging workflow
     *
     * @param string $error Error message, stack trace, or description of the issue
     * @param string $context Context area (controller, model, database, view)
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $error,
        string $context = '',
    ): array {
        if ($context !== '' && $context !== '0') {
            $this->validateEnumParameter(
                $context,
                ['controller', 'model', 'database', 'view'],
                'context',
                'debug-helper',
            );
        }

        $contextHint = $context !== '' && $context !== '0' ? sprintf(' in the %s layer', $context) : '';

        return [
            Response::text(<<<TEXT
I need help debugging this CakePHP issue{$contextHint}:

{$error}

Please help me:
1. Search the CakePHP {$this->cakephpVersion} documentation for relevant information about this error
2. Identify the most likely causes based on CakePHP conventions
3. Suggest specific diagnostic steps I can take
4. Recommend fixes with code examples following CakePHP {$this->cakephpVersion}
   best practices
5. If relevant, suggest tinker commands to test the fix

Use the available tools (search_docs, get_doc, tinker) to gather
information and provide comprehensive guidance.
TEXT),
        ];
    }
}
