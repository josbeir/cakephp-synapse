<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Documentation Expert Prompt
 *
 * Comprehensive CakePHP feature exploration with configurable depth levels.
 */
class DocumentationExpertPrompt extends AbstractPrompt
{
    /**
     * Get comprehensive guidance on a CakePHP feature
     *
     * @param string $topic The CakePHP feature or topic to explore
     * @param string $depth Detail level: "basic", "intermediate", or "advanced"
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'documentation-expert',
        description: 'Get comprehensive guidance on a CakePHP feature with examples and best practices',
    )]
    public function handle(
        string $topic,
        string $depth = 'intermediate',
    ): array {
        return match ($depth) {
            'basic' => $this->getBasicDocumentationMessages($topic),
            'advanced' => $this->getAdvancedDocumentationMessages($topic),
            default => $this->getIntermediateDocumentationMessages($topic),
        };
    }

    /**
     * Get basic documentation messages
     *
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    private function getBasicDocumentationMessages(string $topic): array
    {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need a basic overview of '{$topic}' in CakePHP {$this->cakephpVersion}.

Please:
1. Search the CakePHP {$this->cakephpVersion} documentation for '{$topic}'
2. Read the main documentation page
3. Provide a concise explanation including:
   - What it is and when to use it
   - A simple, runnable code example
   - Key concepts to understand
   - Link to the full documentation

Keep it brief and beginner-friendly. Focus on getting started quickly.
TEXT,
                ),
            ),
        ];
    }

    /**
     * Get intermediate documentation messages
     *
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    private function getIntermediateDocumentationMessages(string $topic): array
    {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need detailed information about '{$topic}' in CakePHP {$this->cakephpVersion}.

Please:
1. Search the CakePHP {$this->cakephpVersion} documentation for '{$topic}'
2. Read the relevant documentation pages thoroughly
3. Provide comprehensive coverage including:
   - Overview and common use cases
   - Multiple usage patterns with practical code examples
   - Configuration options and their effects
   - Best practices and common pitfalls
   - How it integrates with other CakePHP features
   - Type hints and return types (PHP {$this->phpVersion}+)
4. If applicable, suggest tinker commands to demonstrate the concept
5. Provide links to related documentation

Aim for practical, production-ready guidance that covers the most common scenarios.
TEXT,
                ),
            ),
        ];
    }

    /**
     * Get advanced documentation messages
     *
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    private function getAdvancedDocumentationMessages(string $topic): array
    {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need an expert-level deep dive into '{$topic}' in CakePHP {$this->cakephpVersion}.

Please:
1. Search CakePHP {$this->cakephpVersion} documentation comprehensively for '{$topic}'
2. Read all related documentation sections, including advanced topics
3. Provide in-depth coverage including:
   - Complete API reference with all available options
   - Advanced usage patterns, edge cases, and lesser-known features
   - Performance considerations and optimization techniques
   - Internal implementation details (when relevant for understanding)
   - Comparison with alternative approaches and when to use each
   - Common pitfalls, gotchas, and how to avoid them
   - Real-world production scenarios and solutions
   - Integration patterns with other systems
4. Provide multiple comprehensive code examples showing:
   - Basic usage
   - Common patterns
   - Advanced techniques
   - Edge case handling
5. Suggest tinker commands for:
   - Testing advanced scenarios
   - Inspecting internal state
   - Verifying assumptions
6. Reference source code locations when helpful

This is for an experienced developer who needs comprehensive, production-grade understanding.
Include performance implications, security considerations, and architectural decisions.
TEXT,
                ),
            ),
        ];
    }
}
