<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Feature Builder Prompt
 *
 * Guide for implementing complete features following CakePHP conventions.
 */
class FeatureBuilderPrompt extends AbstractPrompt
{
    /**
     * Feature implementation guide
     *
     * @param string $feature Feature description
     * @param string $component Component type (controller, model, behavior, helper, middleware, command, full-stack)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'feature-builder',
        description: 'Guide for implementing a complete feature following CakePHP conventions',
    )]
    public function handle(
        string $feature,
        string $component = 'full-stack',
    ): array {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I want to implement this feature in my CakePHP {$this->cakephpVersion} application: {$feature}

Component focus: {$component}

Please help me by:
1. Searching the CakePHP {$this->cakephpVersion} documentation for relevant patterns and examples
2. Reading detailed documentation on the recommended approach
3. Outlining the implementation steps following CakePHP conventions
4. Providing code examples for each component (controllers, models, views, etc.)
5. Suggesting test cases and how to verify the implementation
6. Recommending any plugins or helpers that could simplify the implementation

Focus on CakePHP {$this->cakephpVersion} best practices, use proper typing (PHP {$this->phpVersion}+),
and follow the framework conventions.
TEXT,
                ),
            ),
        ];
    }
}
