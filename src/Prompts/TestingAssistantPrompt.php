<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Testing Assistant Prompt
 *
 * Generate test cases and testing guidance.
 */
#[Name('testing-assistant')]
#[Description('Generate test cases and testing guidance')]
class TestingAssistantPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('subject', 'What to test (description or code snippet)', true),
            new Argument('testType', 'Test type (unit, integration, fixture, all)', false),
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

        return $this->render($arguments['subject'], $arguments['testType'] ?? 'all');
    }

    /**
     * Generate test cases and guidance
     *
     * @param string $subject What to test (description or code snippet)
     * @param string $testType Test type (unit, integration, fixture, all)
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $subject,
        string $testType = 'all',
    ): array {
        $this->validateEnumParameter(
            $testType,
            ['unit', 'integration', 'fixture', 'all'],
            'testType',
            'testing-assistant',
        );

        return [
            Response::text(<<<TEXT
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
TEXT),
        ];
    }
}
