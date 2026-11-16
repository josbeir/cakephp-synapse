<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Tinker Workshop Prompt
 *
 * Guide for interactive PHP exploration and testing using tinker.
 */
#[Name('tinker-workshop')]
#[Description('Guide for interactive PHP exploration and testing using tinker')]
class TinkerWorkshopPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('goal', 'Goal: "explore", "test", or "debug"', true),
            new Argument('subject', 'What you want to explore/test/debug', false),
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

        return $this->render($arguments['goal'], $arguments['subject'] ?? '');
    }

    /**
     * Interactive PHP exploration and testing guide
     *
     * @param string $goal Goal: "explore", "test", or "debug"
     * @param string $subject What you want to explore/test/debug
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $goal,
        string $subject = '',
    ): array {
        $this->validateEnumParameter(
            $goal,
            ['explore', 'test', 'debug'],
            'goal',
            'tinker-workshop',
        );

        $subjectHint = $subject !== '' && $subject !== '0' ? ' with ' . $subject : '';

        return [
            Response::text(<<<TEXT
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
TEXT),
        ];
    }
}
