<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Performance Analyzer Prompt
 *
 * Analyze and optimize performance issues.
 */
class PerformanceAnalyzerPrompt extends AbstractPrompt
{
    /**
     * Analyze and optimize performance
     *
     * @param string $concern Performance concern (e.g., "slow queries", "memory usage")
     * @param string $context Context or code snippet showing the issue
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'performance-analyzer',
        description: 'Analyze and optimize performance issues',
    )]
    public function handle(
        string $concern,
        string $context = '',
    ): array {
        $this->validateNonEmptyParameter($concern, 'concern', 'performance-analyzer');

        $contextSection = $context !== '' && $context !== '0' ? '

Context:
' . $context : '';

        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I have a performance issue: {$concern}{$contextSection}

Please help by:
1. Searching CakePHP {$this->cakephpVersion} documentation for performance best practices
2. Reading guides on caching, query optimization, and profiling
3. Analyzing the issue and identifying likely bottlenecks
4. Recommending specific optimizations:
   - Query optimization (eager loading with contain(), proper indexes, query caching)
   - Caching strategies (query cache, view cache, cache configuration)
   - Code improvements (eliminate N+1 queries, use lazy loading appropriately)
   - Configuration tuning (opcache, database settings)
   - Use of built-in profiling tools (DebugKit, query logging)
5. Providing before/after code examples
6. Suggesting profiling/debugging steps using tinker or DebugKit
7. Estimating the impact of each optimization

Focus on CakePHP {$this->cakephpVersion}-specific solutions and built-in optimization features.
TEXT,
                ),
            ),
        ];
    }
}
