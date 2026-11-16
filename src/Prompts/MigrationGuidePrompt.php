<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Migration Guide Prompt
 *
 * Help migrate code between CakePHP versions.
 */
#[Name('migration-guide')]
#[Description('Help migrate code between CakePHP versions')]
class MigrationGuidePrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('fromVersion', 'Current version (e.g., "4.5", "3.10")', true),
            new Argument('toVersion', 'Target version (e.g., "5.2")', true),
            new Argument('area', 'Specific feature area or "general"', false),
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

        return $this->render($arguments['fromVersion'], $arguments['toVersion'], $arguments['area'] ?? 'general');
    }

    /**
     * Help migrate between CakePHP versions
     *
     * @param string $fromVersion Current version (e.g., "4.5", "3.10")
     * @param string $toVersion Target version (e.g., "5.2")
     * @param string $area Specific feature area or "general"
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $fromVersion,
        string $toVersion,
        string $area = 'general',
    ): array {
        $this->validateNonEmptyParameter($fromVersion, 'fromVersion', 'migration-guide');
        $this->validateNonEmptyParameter($toVersion, 'toVersion', 'migration-guide');

        return [
            Response::text(<<<TEXT
I need to migrate my CakePHP application from version {$fromVersion} to {$toVersion}.

Area of focus: {$area}

Please help by:
1. Searching for migration guides and upgrade documentation
2. Reading the changelog and breaking changes documentation
3. Check if a path with the CakePHP [upgrade tool](https://github.com/cakephp/upgrade) is possible
4. Identifying deprecated features I should update
5. Finding new features I can leverage
6. Providing a step-by-step migration checklist

For the '{$area}' area specifically:
- List all breaking changes
- Show before/after code examples
- Explain the reasoning behind changes
- Suggest modern alternatives to deprecated features
- Point out new features that could simplify my code
- Highlight PHP version requirements and new language features (PHP {$this->phpVersion}+) I can use

Include links to relevant documentation sections.
TEXT),
        ];
    }
}
