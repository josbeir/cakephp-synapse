<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Feature Builder Prompt
 *
 * Guide for implementing complete features following CakePHP conventions.
 */
#[Name('feature-builder')]
#[Description('Guide for implementing a complete feature following CakePHP conventions')]
class FeatureBuilderPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('feature', 'Feature description', true),
            new Argument(
                'component',
                'Component type (controller, model, behavior, helper, middleware, command, full-stack)',
                false,
            ),
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

        return $this->render($arguments['feature'], $arguments['component'] ?? 'full-stack');
    }

    /**
     * Feature implementation guide
     *
     * @param string $feature Feature description
     * @param string $component Component type (controller, model, behavior, helper, middleware, command, full-stack)
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
        string $feature,
        string $component = 'full-stack',
    ): array {
        $this->validateEnumParameter(
            $component,
            ['controller', 'model', 'behavior', 'helper', 'middleware', 'command', 'full-stack'],
            'component',
            'feature-builder',
        );

        return [
            Response::text(<<<TEXT
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
TEXT),
        ];
    }
}
