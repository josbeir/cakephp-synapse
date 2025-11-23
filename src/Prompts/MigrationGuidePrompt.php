<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Migration Guide Prompt
 *
 * Help migrate code between CakePHP versions.
 */
class MigrationGuidePrompt extends AbstractPrompt
{
    /**
     * Help migrate between CakePHP versions
     *
     * @param string $fromVersion Current version (e.g., "4.5", "3.10")
     * @param string $toVersion Target version (e.g., "5.2")
     * @param string $area Specific feature area or "general"
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'migration-guide',
        description: 'Help migrate code between CakePHP versions',
    )]
    public function handle(
        string $fromVersion,
        string $toVersion,
        string $area = 'general',
    ): array {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
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
TEXT,
                ),
            ),
        ];
    }
}
