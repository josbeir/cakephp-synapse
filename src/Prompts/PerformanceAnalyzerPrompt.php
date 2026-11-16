<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Performance Analyzer Prompt
 *
 * Analyze and optimize performance issues.
 */
#[Name('performance-analyzer')]
#[Description('Analyze and optimize performance issues')]
class PerformanceAnalyzerPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('concern', 'Performance concern (e.g., "slow queries", "memory usage")', true),
            new Argument('context', 'Context or code snippet showing the issue', false),
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

        return $this->render($arguments['concern'], $arguments['context'] ?? '');
    }

    /**
     * Analyze and optimize performance
     *
     * @param string $concern Performance concern (e.g., "slow queries", "memory usage")
     * @param string $context Context or code snippet showing the issue
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $concern,
        string $context = '',
    ): array {
        $this->validateNonEmptyParameter($concern, 'concern', 'performance-analyzer');

        $contextSection = $context !== '' && $context !== '0' ? '

Context:
' . $context : '';

        return [
            Response::text(<<<TEXT
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
TEXT),
        ];
    }
}
