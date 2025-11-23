<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Tinker Workshop Prompt
 *
 * Guide for interactive PHP exploration and testing using tinker.
 */
class TinkerWorkshopPrompt extends AbstractPrompt
{
    /**
     * Interactive PHP exploration and testing guide
     *
     * @param string $goal Goal: "explore", "test", or "debug"
     * @param string $subject What you want to explore/test/debug
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'tinker-workshop',
        description: 'Guide for interactive PHP exploration and testing using tinker',
    )]
    public function handle(
        string $goal,
        string $subject = '',
    ): array {
        $subjectHint = $subject !== '' && $subject !== '0' ? ' with ' . $subject : '';

        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I want to use tinker to {$goal}{$subjectHint}.

The tinker tool allows executing PHP code with access to:
- \$this->fetchTable() - Get any table instance
- \$this->getTableLocator() - Access the table locator
- \$this->log() - Log messages for debugging

Please help me by:
1. Searching CakePHP {$this->cakephpVersion} documentation for relevant information
2. Providing a series of tinker commands to accomplish my goal
3. Explaining what each command does
4. Showing expected output
5. Suggesting follow-up experiments

For exploration:
- Show how to inspect objects, methods, and properties
- Demonstrate reflection techniques
- Explain component relationships

For testing:
- Provide code snippets to verify behavior
- Show assertion-like checks
- Demonstrate edge cases

For debugging:
- Show diagnostic commands
- Inspect state and data
- Trace execution flow

Use var_export(), print_r(), or dump() to display results clearly.
TEXT,
                ),
            ),
        ];
    }
}
