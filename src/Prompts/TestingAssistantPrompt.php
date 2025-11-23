<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Testing Assistant Prompt
 *
 * Generate test cases and testing guidance.
 */
class TestingAssistantPrompt extends AbstractPrompt
{
    /**
     * Generate test cases and guidance
     *
     * @param string $subject What to test (description or code snippet)
     * @param string $testType Test type (unit, integration, fixture, all)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'testing-assistant',
        description: 'Generate test cases and testing guidance',
    )]
    public function handle(
        string $subject,
        string $testType = 'all',
    ): array {
        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need to write tests for: {$subject}

Test type: {$testType}

Please:
1. Search CakePHP {$this->cakephpVersion} documentation for testing best practices
2. Read testing guides for relevant components
3. Identify test scenarios:
   - Happy path cases
   - Edge cases
   - Error conditions
   - Boundary conditions
4. Generate test code including:
   - Test class structure with proper namespace and imports
   - setUp/tearDown methods
   - Individual test methods with clear names and assertions
   - Fixture definitions (if applicable)
   - Mock objects (if needed)
   - Data providers for parameterized tests
5. Explain what each test validates
6. Suggest additional test coverage
7. Provide tinker commands to manually verify behavior

Follow CakePHP {$this->cakephpVersion} testing conventions:
- Use PHPUnit assertions
- Proper test isolation
- Factory patterns for test data
- Integration test helpers
TEXT,
                ),
            ),
        ];
    }
}
