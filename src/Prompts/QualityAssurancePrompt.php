<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Cake\Core\Configure;
use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Quality Assurance Prompt
 *
 * Provides coding guidelines and QA best practices for CakePHP applications.
 * Configurable to include only the tools enabled in your project.
 */
class QualityAssurancePrompt extends AbstractPrompt
{
    /**
     * Quality tools configuration
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $qualityTools;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->qualityTools = Configure::read('Synapse.prompts.quality_tools', [
            'phpcs' => ['enabled' => true, 'standard' => 'cakephp', 'extensions' => ['php']],
            'phpstan' => ['enabled' => true, 'level' => 8, 'baseline' => false],
            'phpunit' => ['enabled' => true, 'coverage' => true, 'coverage_threshold' => 80],
            'rector' => ['enabled' => true, 'set' => 'cakephp'],
            'psalm' => ['enabled' => false, 'level' => 3],
        ]);
    }

    /**
     * Get coding guidelines and QA best practices
     *
     * @param string $context What information to focus on (guidelines, integration, troubleshooting, all)
     * @param string $tools Override enabled tools for this request (all, or comma-separated: phpcs,phpstan)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'quality-assurance',
        description: 'Get coding guidelines and QA best practices for CakePHP',
    )]
    public function handle(
        string $context = 'all',
        string $tools = 'all',
    ): array {
        $enabledTools = $this->getEnabledTools($tools);

        if ($enabledTools === []) {
            return [
                new PromptMessage(
                    role: Role::User,
                    content: new TextContent(
                        text: 'No quality assurance tools are enabled. ' .
                        'Please configure tools in config/synapse.php under Synapse.prompts.quality_tools.',
                    ),
                ),
            ];
        }

        $content = $this->buildPromptContent($context, $enabledTools);

        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: $content,
                ),
            ),
        ];
    }

    /**
     * Get list of enabled tools
     *
     * @param string $toolsFilter Tool filter (all or comma-separated list)
     * @return array<string>
     */
    protected function getEnabledTools(string $toolsFilter): array
    {
        $enabled = [];

        foreach ($this->qualityTools as $tool => $config) {
            if (!empty($config['enabled'])) {
                $enabled[] = $tool;
            }
        }

        if ($toolsFilter !== 'all') {
            $filtered = array_map('trim', explode(',', $toolsFilter));
            $enabled = array_intersect($enabled, $filtered);
        }

        return array_values($enabled);
    }

    /**
     * Build prompt content based on context and enabled tools
     *
     * @param string $context Context mode
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function buildPromptContent(string $context, array $enabledTools): string
    {
        $content = "# CakePHP Quality Assurance Guide\n\n";
        $content .= sprintf('**CakePHP Version:** %s%s', $this->cakephpVersion, PHP_EOL);
        $content .= "**PHP Version:** {$this->phpVersion}\n\n";

        $content .= "## Enabled Tools\n\n";
        foreach ($enabledTools as $tool) {
            $config = $this->qualityTools[$tool];
            $content .= $this->getToolSummary($tool, $config);
        }

        $content .= "\n---\n\n";
        $content .= $this->getConfigurationWarning($enabledTools);
        $content .= "---\n\n";

        if (in_array($context, ['guidelines', 'all'])) {
            $content .= $this->getGuidelinesSection($enabledTools);
        }

        if (in_array($context, ['integration', 'all'])) {
            $content .= $this->getIntegrationSection($enabledTools);
        }

        if (in_array($context, ['troubleshooting', 'all'])) {
            $content .= $this->getTroubleshootingSection($enabledTools);
        }

        return $content . $this->getBestPracticesSection($enabledTools);
    }

    /**
     * Get tool summary line
     *
     * @param string $tool Tool name
     * @param array<string, mixed> $config Tool configuration
     */
    protected function getToolSummary(string $tool, array $config): string
    {
        $summary = sprintf('✓ **%s**', $this->getToolDisplayName($tool));

        switch ($tool) {
            case 'phpcs':
                $summary .= sprintf(' (Standard: %s)', $config['standard']);
                break;
            case 'phpstan':
                $summary .= sprintf(' (Level: %s)', $config['level']);
                if ($config['baseline']) {
                    $summary .= ' with baseline';
                }

                break;
            case 'phpunit':
                if ($config['coverage']) {
                    $summary .= sprintf(' (Coverage: %s%%)', $config['coverage_threshold']);
                }

                break;
            case 'rector':
                $summary .= sprintf(' (Set: %s)', $config['set']);
                break;
            case 'psalm':
                $summary .= sprintf(' (Level: %s)', $config['level']);
                break;
        }

        return $summary . "\n";
    }

    /**
     * Get tool display name
     *
     * @param string $tool Tool identifier
     */
    protected function getToolDisplayName(string $tool): string
    {
        return match ($tool) {
            'phpcs' => 'PHPCS (Code Standards)',
            'phpstan' => 'PHPStan (Static Analysis)',
            'phpunit' => 'PHPUnit (Testing)',
            'rector' => 'Rector (Refactoring)',
            'psalm' => 'Psalm (Static Analysis)',
            default => ucfirst($tool),
        };
    }

    /**
     * Get configuration warning section
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getConfigurationWarning(array $enabledTools): string
    {
        $configFiles = [];
        foreach ($enabledTools as $tool) {
            $file = match ($tool) {
                'phpcs' => '`phpcs.xml`',
                'phpstan' => '`phpstan.neon`',
                'phpunit' => '`phpunit.xml`',
                'rector' => '`rector.php`',
                'psalm' => '`psalm.xml`',
                default => null,
            };
            if ($file !== null) {
                $configFiles[] = $file;
            }
        }

        $configFilesList = implode(', ', $configFiles);

        $example = '';
        if (in_array('phpstan', $enabledTools)) {
            $example = 'Example: If `phpstan.neon` exists, run `vendor/bin/phpstan analyze` ' .
                "without additional arguments like `--level` or `--configuration`.\n\n";
        } elseif (in_array('phpcs', $enabledTools)) {
            $example = "Example: If `phpcs.xml` exists, run `vendor/bin/phpcs` without specifying `--standard`.\n\n";
        }

        return <<<TEXT
## Important: Configuration Files First

**ALWAYS check for existing configuration files in the project before suggesting commands.**

When these tools have configuration files present (e.g., {$configFilesList}), the tools will
automatically use those configurations. **Do not add command-line arguments that override
these configurations** unless explicitly requested.

{$example}
TEXT;
    }

    /**
     * Get guidelines section
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getGuidelinesSection(array $enabledTools): string
    {
        $content = <<<TEXT
## Coding Guidelines & Best Practices

### General CakePHP {$this->cakephpVersion} Standards

- Follow PSR-12 coding style with CakePHP conventions
- Use PHP {$this->phpVersion}+ features (constructor property promotion, readonly, union types)
- Type hint everything (parameters, return types, properties)
- Use strict types declaration: `declare(strict_types=1);`
- Keep code DRY (Don't Repeat Yourself)
- Follow SOLID principles
- Write self-documenting code with clear names

TEXT;

        if (in_array('phpcs', $enabledTools)) {
            $content .= $this->getPhpcsGuidelines();
        }

        if (in_array('phpstan', $enabledTools)) {
            $content .= $this->getPhpstanGuidelines();
        }

        if (in_array('phpunit', $enabledTools)) {
            $content .= $this->getPhpunitGuidelines();
        }

        if (in_array('rector', $enabledTools)) {
            $content .= $this->getRectorGuidelines();
        }

        if (in_array('psalm', $enabledTools)) {
            $content .= $this->getPsalmGuidelines();
        }

        return $content;
    }

    /**
     * Get PHPCS guidelines
     */
    protected function getPhpcsGuidelines(): string
    {
        return <<<'TEXT'
        ### PHPCS Guidelines

        **Common CakePHP Conventions:**
        - Class names: `PascalCase` (e.g., `UsersTable`, `ArticlesController`)
        - Method names: `camelCase` (e.g., `findActiveUsers()`)
        - Property names: `camelCase` (e.g., `$userName`)
        - Constants: `SCREAMING_SNAKE_CASE` (e.g., `DEFAULT_LIMIT`)
        - File names match class names

        **Docblocks:**
        - Use fully qualified class names (FQDN) in all docblocks (e.g., `@var \App\Model\Entity\User`)

        **Formatting Rules:**
        - Indentation: 4 spaces (no tabs)
        - Line length: 120 characters max (soft limit)
        - Opening braces on same line for methods/functions
        - One blank line after namespace declaration
        - Use statement order: classes, functions, constants

        **Common Violations to Avoid:**
        - Missing docblocks for public methods
        - Incorrect indentation
        - Trailing whitespace
        - Missing blank line at end of file
        - Unused use statements

        ---

TEXT;
    }

    /**
     * Get PHPStan guidelines
     */
    protected function getPhpstanGuidelines(): string
    {
        $level = $this->qualityTools['phpstan']['level'];

        return <<<TEXT
### PHPStan Guidelines (Level {$level})

**Type Safety Best Practices:**
- Always type hint function parameters and return types
- Use `@var` annotations for properties with complex types
- Use `@return` annotations for array shapes
- Prefer typed arrays: `array<int, string>` over `array`
- Use union types when appropriate: `string|int`

**Common Issues to Avoid:**
- Accessing properties that might not exist
- Calling methods on possibly null values
- Array access on non-arrays
- Missing return type declarations
- Unused variables

**CakePHP-Specific Patterns:**
- Use proper type hints for Table methods: `Query`, `EntityInterface`
- Type hint controller actions with `ResponseInterface` return
- Use proper annotations for ORM relationships
- Document dynamic properties with `@property` annotations

**Example - Properly Typed Controller:**
```php
public function index(): ResponseInterface
{
    \$articles = \$this->Articles->find('all')
        ->contain(['Authors'])
        ->limit(10);

    \$this->set(compact('articles'));

    return \$this->render();
}
```

---


TEXT;
    }

    /**
     * Get PHPUnit guidelines
     */
    protected function getPhpunitGuidelines(): string
    {
        return <<<'TEXT'
### PHPUnit Guidelines

**Test Structure:**
- One test class per source class
- Use descriptive test method names: `testFindActiveUsersReturnsOnlyActiveRecords()`
- Follow Arrange-Act-Assert pattern
- Keep tests focused (test one thing per test)
- Use data providers for testing multiple scenarios

**CakePHP Testing Best Practices:**
- Use fixtures for database tests
- Use `IntegrationTestCase` for controller/HTTP tests
- Use `TestCase` for unit tests
- Mock external dependencies
- Test edge cases and error conditions

**Coverage Best Practices:**
- Aim for high coverage but focus on meaningful tests
- Don't test framework code (CakePHP internals)
- Test business logic thoroughly
- Test error handling and validation
- Test different execution paths

**Example - Table Test:**
```php
public function testFindActiveReturnsOnlyActiveUsers(): void
{
    $result = $this->Users->find('active')->toArray();

    $this->assertCount(3, $result);
    $this->assertTrue($result[0]->active);
}
```

**Example - Controller Test:**
```php
public function testIndexDisplaysArticles(): void
{
    $this->get('/articles');

    $this->assertResponseOk();
    $this->assertResponseContains('Articles List');
}
```

---


TEXT;
    }

    /**
     * Get Rector guidelines
     */
    protected function getRectorGuidelines(): string
    {
        return <<<'TEXT'
### Rector Guidelines

**Safe Refactoring Practices:**
- Always run with `--dry-run` first
- Review changes carefully before committing
- Run tests after applying changes
- Use version control (git diff) to review changes
- Apply changes incrementally

**Recommended Rector Sets:**
- `PHP_82` or `PHP_83` - Upgrade to modern PHP features
- `CODE_QUALITY` - Improve code quality
- `DEAD_CODE` - Remove dead code
- `TYPE_DECLARATION` - Add type declarations
- `EARLY_RETURN` - Simplify if-else chains

**What Rector Can Do:**
- Add type declarations automatically
- Convert to constructor property promotion
- Modernize code to use latest PHP features
- Remove unused code
- Refactor code quality issues

**CakePHP-Specific Considerations:**
- Exclude generated code (migrations)
- Review ORM-related changes carefully
- Test thoroughly after refactoring
- Be careful with magic methods and properties

---


TEXT;
    }

    /**
     * Get Psalm guidelines
     */
    protected function getPsalmGuidelines(): string
    {
        return <<<'TEXT'
### Psalm Guidelines

**Psalm-Specific Features:**
- More strict about null safety
- Better inference for array shapes
- Template (generic) type support
- Immutability checking

**When to Use Psalm:**
- When you want stricter type checking than PHPStan
- When working with complex generic types
- When you need immutability guarantees
- As a complement to PHPStan for extra confidence

**Psalm Annotations:**
- `@psalm-param` - Parameter type hints
- `@psalm-return` - Return type hints
- `@psalm-var` - Variable type hints
- `@psalm-suppress` - Suppress specific issues

---


TEXT;
    }

    /**
     * Get integration section
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getIntegrationSection(array $enabledTools): string
    {
        $content = "## Integration & Automation\n\n";

        $content .= $this->getComposerScripts($enabledTools);
        $content .= $this->getGitHooks($enabledTools);

        return $content . $this->getCiCdExamples($enabledTools);
    }

    /**
     * Get composer scripts examples
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getComposerScripts(array $enabledTools): string
    {
        $scripts = [];

        if (in_array('phpcs', $enabledTools)) {
            $scripts[] = '        "cs-check": "phpcs",';
            $scripts[] = '        "cs-fix": "phpcbf",';
        }

        if (in_array('phpstan', $enabledTools)) {
            $scripts[] = '        "stan": "phpstan analyze",';
        }

        if (in_array('phpunit', $enabledTools)) {
            $scripts[] = '        "test": "phpunit --colors=always",';
            if ($this->qualityTools['phpunit']['coverage'] ?? false) {
                $scripts[] = '        "test-coverage": "phpunit --coverage-html tmp/coverage",';
            }
        }

        if (in_array('rector', $enabledTools)) {
            $scripts[] = '        "rector-dry": "rector process --dry-run",';
            $scripts[] = '        "rector-fix": "rector process",';
        }

        if (in_array('psalm', $enabledTools)) {
            $scripts[] = '        "psalm": "psalm",';
        }

        $qaCommands = [];
        if (in_array('phpcs', $enabledTools)) {
            $qaCommands[] = '@cs-check';
        }

        if (in_array('phpstan', $enabledTools)) {
            $qaCommands[] = '@stan';
        }

        if (in_array('phpunit', $enabledTools)) {
            $qaCommands[] = '@test';
        }

        if (in_array('psalm', $enabledTools)) {
            $qaCommands[] = '@psalm';
        }

        if ($qaCommands !== []) {
            $scripts[] = '        "qa": [';
            foreach ($qaCommands as $cmd) {
                $scripts[] = '            "' . $cmd . '",';
            }

            $scripts[] = '        ],';
        }

        $scriptsString = implode("\n", $scripts);

        return <<<TEXT
### Composer Scripts

Add these scripts to your `composer.json` for easy command execution:

```json
{
    "scripts": {
{$scriptsString}
    }
}
```

**Usage:**
```bash
# Run all QA checks
composer qa

# Individual commands
composer cs-check
composer stan
composer test
```

---


TEXT;
    }

    /**
     * Get git hooks examples
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getGitHooks(array $enabledTools): string
    {
        $checks = [];

        if (in_array('phpcs', $enabledTools)) {
            $checks[] = 'vendor/bin/phpcs';
        }

        if (in_array('phpstan', $enabledTools)) {
            $checks[] = 'vendor/bin/phpstan analyze';
        }

        if (in_array('phpunit', $enabledTools)) {
            $checks[] = 'vendor/bin/phpunit';
        }

        $checksString = implode(" && \\\n    ", $checks);

        return <<<TEXT
### Git Pre-commit Hook

Create `.git/hooks/pre-commit` to run checks before each commit:

```bash
#!/bin/bash

echo "Running QA checks..."

{$checksString}

if [ \$? -ne 0 ]; then
    echo "QA checks failed. Commit aborted."
    exit 1
fi

echo "QA checks passed!"
exit 0
```

Make it executable:
```bash
chmod +x .git/hooks/pre-commit
```

**Note:** Consider using a tool like `husky` or `grumphp` for more sophisticated git hook management.

---


TEXT;
    }

    /**
     * Get CI/CD examples
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getCiCdExamples(array $enabledTools): string
    {
        $steps = [];

        if (in_array('phpcs', $enabledTools)) {
            $steps[] = <<<'YAML'
      - name: PHPCS Check
        run: vendor/bin/phpcs
YAML;
        }

        if (in_array('phpstan', $enabledTools)) {
            $steps[] = <<<'YAML'
      - name: PHPStan Analysis
        run: vendor/bin/phpstan analyze
YAML;
        }

        if (in_array('phpunit', $enabledTools)) {
            $coverage = $this->qualityTools['phpunit']['coverage'] ?? false
                ? ' --coverage-text --coverage-clover coverage.xml'
                : '';
            $steps[] = <<<YAML
      - name: PHPUnit Tests
        run: vendor/bin/phpunit{$coverage}
YAML;
        }

        if (in_array('psalm', $enabledTools)) {
            $steps[] = <<<'YAML'
      - name: Psalm Analysis
        run: vendor/bin/psalm
YAML;
        }

        $stepsString = implode("\n\n", $steps);

        return <<<TEXT
### CI/CD Integration (GitHub Actions)

Create `.github/workflows/qa.yml`:

```yaml
name: QA Checks

on: [push, pull_request]

jobs:
  qa:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '{$this->phpVersion}'
          extensions: mbstring, intl, pdo_sqlite
          coverage: xdebug

      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress

{$stepsString}
```

**GitLab CI Example** (`.gitlab-ci.yml`):

```yaml
qa:
  image: php:{$this->phpVersion}
  script:
    - composer install
{$this->getGitLabSteps($enabledTools)}
```

---


TEXT;
    }

    /**
     * Get GitLab CI steps
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getGitLabSteps(array $enabledTools): string
    {
        $steps = [];

        if (in_array('phpcs', $enabledTools)) {
            $steps[] = '    - vendor/bin/phpcs';
        }

        if (in_array('phpstan', $enabledTools)) {
            $steps[] = '    - vendor/bin/phpstan analyze';
        }

        if (in_array('phpunit', $enabledTools)) {
            $steps[] = '    - vendor/bin/phpunit';
        }

        if (in_array('psalm', $enabledTools)) {
            $steps[] = '    - vendor/bin/psalm';
        }

        return implode("\n", $steps);
    }

    /**
     * Get troubleshooting section
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getTroubleshootingSection(array $enabledTools): string
    {
        $content = "## Troubleshooting\n\n";

        if (in_array('phpcs', $enabledTools)) {
            $content .= <<<'TEXT'
### PHPCS Issues

**Problem:** Too many errors, can't fix all at once
**Solution:** Fix file by file or category by category:
```bash
# Fix one file at a time
vendor/bin/phpcbf src/Controller/UsersController.php

# Fix specific sniff
vendor/bin/phpcbf --sniffs=CakePHP.Commenting.FunctionComment
```

---


TEXT;
        }

        if (in_array('phpstan', $enabledTools)) {
            $content .= <<<'TEXT'
### PHPStan Issues

**Problem:** "Class not found" errors
**Solution:** Make sure autoload is configured in composer.json and run:
```bash
composer dump-autoload
vendor/bin/phpstan clear-result-cache
```

**Problem:** Too many errors on existing project
**Solution:** Use baseline to track existing issues:
```bash
vendor/bin/phpstan analyze --generate-baseline
```
Then gradually fix issues and update baseline.

**Problem:** False positives
**Solution:** Use inline suppressions sparingly:
```php
/** @phpstan-ignore-next-line */
$value = $this->someDynamicMethod();
```

---


TEXT;
        }

        if (in_array('phpunit', $enabledTools)) {
            $content .= <<<'TEXT'
### PHPUnit Issues

**Problem:** "No code coverage driver available"
**Solution:** Install Xdebug or PCOV:
```bash
# Xdebug
pecl install xdebug

# or PCOV (faster)
pecl install pcov
```

**Problem:** Tests fail in CI but pass locally
**Solution:** Check for:
- Database fixture issues
- Timezone differences
- Environment-specific configuration
- Cached data

**Problem:** Slow tests
**Solution:**
- Use fixtures efficiently
- Mock external dependencies
- Use `@group` annotations to run subsets
- Consider using PCOV instead of Xdebug for coverage

---


TEXT;
        }

        if (in_array('rector', $enabledTools)) {
            $content .= <<<'TEXT'
### Rector Issues

**Problem:** Rector breaks code
**Solution:**
- Always use `--dry-run` first
- Apply changes incrementally
- Review git diff before committing
- Run tests after each Rector run

**Problem:** Rector too aggressive
**Solution:** Configure skip rules in `rector.php`:
```php
->withSkip([
    SpecificRectorRule::class,
    SpecificRectorRule::class => [
        __DIR__ . '/src/LegacyCode',
    ],
])
```

---


TEXT;
        }

        if (in_array('psalm', $enabledTools)) {
            $content .= <<<'TEXT'
### Psalm Issues

**Problem:** Too strict, many errors
**Solution:** Start with a higher error level (less strict) and work down:
```bash
vendor/bin/psalm --set-baseline=psalm-baseline.xml
```

**Problem:** Conflicts with PHPStan annotations
**Solution:** Use Psalm-specific annotations where needed:
```php
/** @psalm-suppress MixedAssignment */
```

---


TEXT;
        }

        return $content;
    }

    /**
     * Get best practices section
     *
     * @param array<string> $enabledTools List of enabled tools
     */
    protected function getBestPracticesSection(array $enabledTools): string
    {
        $content = "## Best Practices Summary\n\n";

        $content .= "### Workflow Recommendations\n\n";
        $content .= "1. **Before Committing:**\n";

        if (in_array('phpcs', $enabledTools)) {
            $content .= "   - Run `vendor/bin/phpcs` to check coding standards\n";
            $content .= "   - Auto-fix with `vendor/bin/phpcbf` when possible\n";
        }

        if (in_array('phpstan', $enabledTools) || in_array('psalm', $enabledTools)) {
            $content .= "   - Run static analysis to catch type errors\n";
        }

        if (in_array('phpunit', $enabledTools)) {
            $content .= "   - Run test suite: `vendor/bin/phpunit`\n";
            if ($this->qualityTools['phpunit']['coverage'] ?? false) {
                $content .= "   - Check coverage meets threshold\n";
            }
        }

        $content .= "\n2. **During Development:**\n";
        $content .= "   - Write tests alongside code (TDD)\n";
        $content .= "   - Use type hints everywhere\n";
        $content .= "   - Follow CakePHP {$this->cakephpVersion} conventions\n";
        $content .= "   - Keep methods small and focused\n";
        $content .= "   - Document complex logic\n";

        $content .= "\n3. **Code Review:**\n";
        $content .= "   - All QA checks must pass\n";
        $content .= "   - Review test coverage for new code\n";
        $content .= "   - Check for code duplication\n";
        $content .= "   - Verify error handling\n";
        $content .= "   - Confirm security best practices\n";

        if (in_array('rector', $enabledTools)) {
            $content .= "\n4. **Periodic Maintenance:**\n";
            $content .= "   - Run Rector to modernize code\n";
            $content .= "   - Update baselines for static analysis\n";
            $content .= "   - Review and improve test coverage\n";
            $content .= "   - Refactor complex methods\n";
        }

        $content .= "\n### Tool Combinations\n\n";
        $content .= "**Recommended Tool Stack:**\n";

        if (in_array('phpcs', $enabledTools) && in_array('phpstan', $enabledTools)) {
            $content .= "- PHPCS + PHPStan = Complete code quality coverage\n";
        }

        if (in_array('phpunit', $enabledTools)) {
            $content .= "- PHPUnit with coverage = Quality metrics and confidence\n";
        }

        if (in_array('rector', $enabledTools) && in_array('phpcs', $enabledTools)) {
            $content .= "- Rector + PHPCS = Automated fixes + validation\n";
        }

        $content .= "\n### CakePHP-Specific Tips\n\n";
        $content .= "- **Configuration Files:** Keep QA tool configs in project root\n";
        $content .= "- **Generated Code:** Exclude from analysis (migrations, bake output)\n";
        $content .= "- **Plugins:** Run QA checks on plugin code separately\n";
        $content .= "- **Framework Code:** Never modify vendor code to pass checks\n";
        $content .= sprintf('- **Type Safety:** Use CakePHP %s type hints ', $this->cakephpVersion) .
            "(Query, EntityInterface, etc.)\n";

        $content .= "\n### Performance Tips\n\n";
        $content .= "- Cache static analysis results (PHPStan tmpDir)\n";
        $content .= "- Use parallel execution where supported\n";
        $content .= "- Run full checks in CI, quick checks locally\n";
        $content .= "- Use baseline files to track progress incrementally\n";

        $content .= "\n---\n\n";
        $content .= '**Remember:** These tools are helpers, not replacements for code review and testing. ';
        $content .= 'Always rely on existing configuration files in your project. ';

        return $content . "Use them to catch issues early and maintain consistent code quality!\n";
    }
}
