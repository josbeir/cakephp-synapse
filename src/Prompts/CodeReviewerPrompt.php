<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Code Reviewer Prompt
 *
 * Review code against CakePHP conventions and best practices.
 */
#[Name('code-reviewer')]
#[Description('Review code against CakePHP conventions and best practices')]
class CodeReviewerPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('code', 'Code snippet to review', true),
            new Argument('focus', 'Focus area (conventions, security, performance, testing, all)', false),
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

        return $this->render($arguments['code'], $arguments['focus'] ?? 'all');
    }

    /**
     * Review code against CakePHP conventions
     *
     * @param string $code Code snippet to review
     * @param string $focus Focus area (conventions, security, performance, testing, all)
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $code,
        string $focus = 'all',
    ): array {
        $this->validateEnumParameter(
            $focus,
            ['conventions', 'security', 'performance', 'testing', 'all'],
            'focus',
            'code-reviewer',
        );

        return [
            Response::text(<<<TEXT
Please review this CakePHP code:

```php
{$code}
```

Focus area: {$focus}

Review for:
1. CakePHP {$this->cakephpVersion} conventions and coding standards
2. Type safety (PHP {$this->phpVersion}+ features: constructor property promotion, readonly, etc.)
3. Security best practices (SQL injection, XSS, CSRF, mass assignment)
4. Performance considerations (N+1 queries, eager loading, caching)
5. Error handling and logging
6. Code organization (DRY, SOLID principles)
7. Documentation and type hints

Search the CakePHP {$this->cakephpVersion} documentation for:
- Relevant conventions this code should follow
- Best practices for similar functionality
- Security guidelines

Provide:
- Specific issues found with line references
- Recommended fixes with corrected code
- Links to relevant documentation
- Explanation of why each change improves the code
TEXT),
        ];
    }
}
