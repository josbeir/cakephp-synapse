<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Debug Helper Prompt
 *
 * Systematic debugging workflow for CakePHP errors and issues.
 */
class DebugHelperPrompt extends AbstractPrompt
{
    /**
     * Systematic debugging workflow
     *
     * @param string $error Error message, stack trace, or description of the issue
     * @param string $context Context area (controller, model, database, view)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'debug-helper',
        description: 'Systematic debugging workflow for CakePHP errors and issues',
    )]
    public function handle(
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
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need help debugging this CakePHP issue{$contextHint}:

{$error}

Please help me:
1. Search the CakePHP {$this->cakephpVersion} documentation for relevant information about this error
2. Identify the most likely causes based on CakePHP conventions
3. Suggest specific diagnostic steps I can take
4. Recommend fixes with code examples following CakePHP {$this->cakephpVersion}
   best practices
5. If relevant, suggest tinker commands to test the fix

Use the available tools (search_docs, read_documentation, tinker) to gather
information and provide comprehensive guidance.
TEXT,
                ),
            ),
        ];
    }
}
