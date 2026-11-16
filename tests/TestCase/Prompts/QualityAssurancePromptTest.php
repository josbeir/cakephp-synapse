<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
use Synapse\Prompts\QualityAssurancePrompt;

/**
 * QualityAssurancePrompt Test Case
 */
class QualityAssurancePromptTest extends TestCase
{
    private QualityAssurancePrompt $prompt;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set default configuration
        Configure::write('Synapse.prompts.cakephp_version', '6.x');
        Configure::write('Synapse.prompts.php_version', '8.5+');
        Configure::write('Synapse.prompts.quality_tools', [
            'phpcs' => ['enabled' => true, 'standard' => 'cakephp', 'extensions' => ['php']],
            'phpstan' => ['enabled' => true, 'level' => 8, 'baseline' => false],
            'phpunit' => ['enabled' => true, 'coverage' => true, 'coverage_threshold' => 80],
            'rector' => ['enabled' => true, 'set' => 'cakephp'],
            'psalm' => ['enabled' => false, 'level' => 3],
        ]);

        $this->prompt = new QualityAssurancePrompt();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->prompt);
        parent::tearDown();
    }

    /**
     * Test handle with all tools enabled and all context
     */
    public function testAllToolsAndAllContext(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'all');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertEquals(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('CakePHP Quality Assurance Guide', $text);
        $this->assertStringContainsString('PHPCS', $text);
        $this->assertStringContainsString('PHPStan', $text);
        $this->assertStringContainsString('PHPUnit', $text);
        $this->assertStringContainsString('Rector', $text);
        $this->assertStringNotContainsString('Psalm', $text); // Psalm is disabled
    }

    /**
     * Test handle with guidelines context
     */
    public function testGuidelinesContext(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'all');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('Coding Guidelines & Best Practices', $text);
        $this->assertStringContainsString('General CakePHP', $text);
        $this->assertStringContainsString('Type hint', $text);
    }

    /**
     * Test handle with integration context
     */
    public function testIntegrationContext(): void
    {
        $result = $this->prompt->render(context: 'integration', tools: 'all');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('Integration & Automation', $text);
        $this->assertStringContainsString('Composer Scripts', $text);
        $this->assertStringContainsString('Git Pre-commit Hook', $text);
        $this->assertStringContainsString('CI/CD', $text);
        $this->assertStringContainsString('GitHub Actions', $text);
    }

    /**
     * Test handle with troubleshooting context
     */
    public function testTroubleshootingContext(): void
    {
        $result = $this->prompt->render(context: 'troubleshooting', tools: 'all');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('Troubleshooting', $text);
        $this->assertStringContainsString('Problem:', $text);
        $this->assertStringContainsString('Solution:', $text);
    }

    /**
     * Test handle with filtered tools
     */
    public function testFilteredTools(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'phpcs,phpstan');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('PHPCS', $text);
        $this->assertStringContainsString('PHPStan', $text);
        // PHPUnit and Rector should not appear in guidelines or tool summaries
        $this->assertStringNotContainsString('PHPUnit (Testing)', $text);
        $this->assertStringNotContainsString('Rector (Refactoring)', $text);
    }

    /**
     * Test handle with single tool
     */
    public function testSingleTool(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'phpcs');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('PHPCS', $text);
        $this->assertStringContainsString('PascalCase', $text);
        // PHPStan should not appear in enabled tools or guidelines
        $this->assertStringNotContainsString('PHPStan (Static Analysis)', $text);
    }

    /**
     * Test handle with no tools enabled
     */
    public function testNoToolsEnabled(): void
    {
        Configure::write('Synapse.prompts.quality_tools', [
            'phpcs' => ['enabled' => false],
            'phpstan' => ['enabled' => false],
            'phpunit' => ['enabled' => false],
            'rector' => ['enabled' => false],
            'psalm' => ['enabled' => false],
        ]);

        $prompt = new QualityAssurancePrompt();
        $result = $prompt->render(context: 'all', tools: 'all');

        $text = (string)$result[0]->content();
        $this->assertStringContainsString('No quality assurance tools are enabled', $text);
        $this->assertStringContainsString('config/synapse.php', $text);
    }

    /**
     * Test uses configured CakePHP version
     */
    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('CakePHP Version:** 4.5', $text);
    }

    /**
     * Test uses configured PHP version
     */
    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('PHP Version:** 8.3', $text);
    }

    /**
     * Test returns valid structure
     */
    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'all');

        $this->assertNotEmpty($result);
        $this->assertContainsOnlyInstancesOf(Response::class, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $this->assertNotEmpty((string)$result[0]->content());
    }

    /**
     * Test PHPCS configuration reflects settings
     */
    public function testPhpcsConfiguration(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'phpcs');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Standard: cakephp', $text);
        $this->assertStringContainsString('PHPCS Guidelines', $text);
    }

    /**
     * Test PHPStan configuration reflects settings
     */
    public function testPhpstanConfiguration(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'phpstan');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Level: 8', $text);
        $this->assertStringContainsString('PHPStan Guidelines (Level 8)', $text);
    }

    /**
     * Test PHPUnit configuration reflects settings
     */
    public function testPhpunitConfiguration(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'phpunit');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Coverage: 80%', $text);
        $this->assertStringContainsString('PHPUnit Guidelines', $text);
    }

    /**
     * Test Rector configuration reflects settings
     */
    public function testRectorConfiguration(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'rector');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Set: cakephp', $text);
        $this->assertStringContainsString('Rector Guidelines', $text);
    }

    /**
     * Test Psalm when enabled
     */
    public function testPsalmWhenEnabled(): void
    {
        Configure::write('Synapse.prompts.quality_tools.psalm.enabled', true);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'guidelines', tools: 'psalm');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Psalm', $text);
        $this->assertStringContainsString('Level: 3', $text);
        $this->assertStringContainsString('Psalm Guidelines', $text);
    }

    /**
     * Test configuration file warning is present
     */
    public function testConfigurationFileWarning(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Configuration Files First', $text);
        $this->assertStringContainsString('ALWAYS check for existing configuration files', $text);
        $this->assertStringContainsString('Do not add command-line arguments that override', $text);
    }

    /**
     * Test composer scripts are included in integration
     */
    public function testComposerScriptsIncluded(): void
    {
        $result = $this->prompt->render(context: 'integration', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('composer.json', $text);
        $this->assertStringContainsString('"scripts"', $text);
        $this->assertStringContainsString('composer qa', $text);
    }

    /**
     * Test git hooks are included in integration
     */
    public function testGitHooksIncluded(): void
    {
        $result = $this->prompt->render(context: 'integration', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('pre-commit', $text);
        $this->assertStringContainsString('chmod +x', $text);
    }

    /**
     * Test CI/CD examples are included in integration
     */
    public function testCiCdExamplesIncluded(): void
    {
        $result = $this->prompt->render(context: 'integration', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('GitHub Actions', $text);
        $this->assertStringContainsString('GitLab CI', $text);
        $this->assertStringContainsString('.github/workflows', $text);
        $this->assertStringContainsString('.gitlab-ci.yml', $text);
    }

    /**
     * Test best practices section is always included
     */
    public function testBestPracticesSectionIncluded(): void
    {
        $result = $this->prompt->render(context: 'guidelines', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Best Practices', $text);
    }

    /**
     * Test PHPStan baseline configuration
     */
    public function testPhpstanWithBaseline(): void
    {
        Configure::write('Synapse.prompts.quality_tools.phpstan.baseline', true);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'phpstan');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('with baseline', $text);
    }

    /**
     * Test PHPUnit without coverage
     */
    public function testPhpunitWithoutCoverage(): void
    {
        Configure::write('Synapse.prompts.quality_tools.phpunit.coverage', false);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'phpunit');
        $text = (string)$result[0]->content();

        // Summary shouldn't show coverage
        $this->assertStringNotContainsString('Coverage: 80%', $text);
    }

    /**
     * Test custom PHPCS standard
     */
    public function testCustomPhpcsStandard(): void
    {
        Configure::write('Synapse.prompts.quality_tools.phpcs.standard', 'PSR12');
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'phpcs');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('Standard: PSR12', $text);
    }

    /**
     * Test tool display names
     */
    public function testToolDisplayNames(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('PHPCS (Code Standards)', $text);
        $this->assertStringContainsString('PHPStan (Static Analysis)', $text);
        $this->assertStringContainsString('PHPUnit (Testing)', $text);
        $this->assertStringContainsString('Rector (Refactoring)', $text);
    }

    /**
     * Test default constructor with missing config
     */
    public function testDefaultConstructorWithMissingConfig(): void
    {
        Configure::delete('Synapse.prompts.quality_tools');
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'all');

        // Should use defaults
        $this->assertCount(1, $result);
    }

    /**
     * Test filtered tools with non-existent tool throws exception
     */
    public function testFilteredToolsWithNonExistent(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid values for parameter 'tools': nonexistent");

        $this->prompt->render(context: 'all', tools: 'phpcs,nonexistent');
    }

    /**
     * Test all enabled tools summary
     */
    public function testAllEnabledToolsSummary(): void
    {
        Configure::write('Synapse.prompts.quality_tools.psalm.enabled', true);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->render(context: 'all', tools: 'all');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('✓ **PHPCS', $text);
        $this->assertStringContainsString('✓ **PHPStan', $text);
        $this->assertStringContainsString('✓ **PHPUnit', $text);
        $this->assertStringContainsString('✓ **Rector', $text);
        $this->assertStringContainsString('✓ **Psalm', $text);
    }

    /**
     * Test configuration warning adapts to enabled tools
     */
    public function testConfigurationWarningAdaptsToTools(): void
    {
        $result = $this->prompt->render(context: 'all', tools: 'phpcs');
        $text = (string)$result[0]->content();

        $this->assertStringContainsString('`phpcs.xml`', $text);
        $this->assertStringNotContainsString('`phpstan.neon`', $text);
    }

    public function testInvalidContextThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'context': 'invalid'");

        $this->prompt->render('invalid');
    }

    public function testInvalidContextContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: guidelines, integration, troubleshooting, all');

        $this->prompt->render('setup');
    }

    public function testInvalidContextContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: quality-assurance');

        $this->prompt->render('bad');
    }

    public function testInvalidToolsThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid values for parameter 'tools': invalid");

        $this->prompt->render('guidelines', 'phpcs,invalid');
    }

    public function testValidContextAndTools(): void
    {
        $result = $this->prompt->render('integration', 'phpcs,phpstan');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }

    public function testDefaultContextAndToolsAreValid(): void
    {
        $result = $this->prompt->render();

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
