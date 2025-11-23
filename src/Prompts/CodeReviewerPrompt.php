<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Code Reviewer Prompt
 *
 * Review code against CakePHP conventions and best practices.
 */
class CodeReviewerPrompt extends AbstractPrompt
{
    /**
     * Review code against CakePHP conventions
     *
     * @param string $code Code snippet to review
     * @param string $focus Focus area (conventions, security, performance, testing, all)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'code-reviewer',
        description: 'Review code against CakePHP conventions and best practices',
    )]
    public function handle(
        string $code,
        string $focus = 'all',
    ): array {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
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
TEXT,
                ),
            ),
        ];
    }
}
