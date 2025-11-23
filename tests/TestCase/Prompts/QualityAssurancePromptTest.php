<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
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
        Configure::write('Synapse.prompts.cakephp_version', '5.x');
        Configure::write('Synapse.prompts.php_version', '8.2+');
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
        $result = $this->prompt->handle(context: 'all', tools: 'all');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertEquals(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);

        $text = $result[0]->content->text;
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
        $result = $this->prompt->handle(context: 'guidelines', tools: 'all');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
        $this->assertStringContainsString('Coding Guidelines & Best Practices', $text);
        $this->assertStringContainsString('General CakePHP', $text);
        $this->assertStringContainsString('Type hint', $text);
    }

    /**
     * Test handle with integration context
     */
    public function testIntegrationContext(): void
    {
        $result = $this->prompt->handle(context: 'integration', tools: 'all');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
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
        $result = $this->prompt->handle(context: 'troubleshooting', tools: 'all');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
        $this->assertStringContainsString('Troubleshooting', $text);
        $this->assertStringContainsString('Problem:', $text);
        $this->assertStringContainsString('Solution:', $text);
    }

    /**
     * Test handle with filtered tools
     */
    public function testFilteredTools(): void
    {
        $result = $this->prompt->handle(context: 'all', tools: 'phpcs,phpstan');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
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
        $result = $this->prompt->handle(context: 'guidelines', tools: 'phpcs');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
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
        $result = $prompt->handle(context: 'all', tools: 'all');

        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;
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

        $result = $prompt->handle(context: 'all', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('CakePHP Version:** 4.5', $text);
    }

    /**
     * Test uses configured PHP version
     */
    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->handle(context: 'all', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('PHP Version:** 8.3', $text);
    }

    /**
     * Test returns valid structure
     */
    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle(context: 'all', tools: 'all');

        $this->assertNotEmpty($result);
        $this->assertContainsOnlyInstancesOf(PromptMessage::class, $result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        $this->assertIsString($result[0]->content->text);
    }

    /**
     * Test PHPCS configuration reflects settings
     */
    public function testPhpcsConfiguration(): void
    {
        $result = $this->prompt->handle(context: 'guidelines', tools: 'phpcs');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Standard: cakephp', $text);
        $this->assertStringContainsString('PHPCS Guidelines', $text);
    }

    /**
     * Test PHPStan configuration reflects settings
     */
    public function testPhpstanConfiguration(): void
    {
        $result = $this->prompt->handle(context: 'guidelines', tools: 'phpstan');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Level: 8', $text);
        $this->assertStringContainsString('PHPStan Guidelines (Level 8)', $text);
    }

    /**
     * Test PHPUnit configuration reflects settings
     */
    public function testPhpunitConfiguration(): void
    {
        $result = $this->prompt->handle(context: 'guidelines', tools: 'phpunit');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Coverage: 80%', $text);
        $this->assertStringContainsString('PHPUnit Guidelines', $text);
    }

    /**
     * Test Rector configuration reflects settings
     */
    public function testRectorConfiguration(): void
    {
        $result = $this->prompt->handle(context: 'guidelines', tools: 'rector');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

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

        $result = $prompt->handle(context: 'guidelines', tools: 'psalm');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Psalm', $text);
        $this->assertStringContainsString('Level: 3', $text);
        $this->assertStringContainsString('Psalm Guidelines', $text);
    }

    /**
     * Test configuration file warning is present
     */
    public function testConfigurationFileWarning(): void
    {
        $result = $this->prompt->handle(context: 'all', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Configuration Files First', $text);
        $this->assertStringContainsString('ALWAYS check for existing configuration files', $text);
        $this->assertStringContainsString('Do not add command-line arguments that override', $text);
    }

    /**
     * Test composer scripts are included in integration
     */
    public function testComposerScriptsIncluded(): void
    {
        $result = $this->prompt->handle(context: 'integration', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('composer.json', $text);
        $this->assertStringContainsString('"scripts"', $text);
        $this->assertStringContainsString('composer qa', $text);
    }

    /**
     * Test git hooks are included in integration
     */
    public function testGitHooksIncluded(): void
    {
        $result = $this->prompt->handle(context: 'integration', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('pre-commit', $text);
        $this->assertStringContainsString('chmod +x', $text);
    }

    /**
     * Test CI/CD examples are included in integration
     */
    public function testCiCdExamplesIncluded(): void
    {
        $result = $this->prompt->handle(context: 'integration', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

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
        $result = $this->prompt->handle(context: 'guidelines', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Best Practices', $text);
    }

    /**
     * Test PHPStan baseline configuration
     */
    public function testPhpstanWithBaseline(): void
    {
        Configure::write('Synapse.prompts.quality_tools.phpstan.baseline', true);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->handle(context: 'all', tools: 'phpstan');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('with baseline', $text);
    }

    /**
     * Test PHPUnit without coverage
     */
    public function testPhpunitWithoutCoverage(): void
    {
        Configure::write('Synapse.prompts.quality_tools.phpunit.coverage', false);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->handle(context: 'all', tools: 'phpunit');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

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

        $result = $prompt->handle(context: 'all', tools: 'phpcs');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('Standard: PSR12', $text);
    }

    /**
     * Test tool display names
     */
    public function testToolDisplayNames(): void
    {
        $result = $this->prompt->handle(context: 'all', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

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

        $result = $prompt->handle(context: 'all', tools: 'all');

        // Should use defaults
        $this->assertCount(1, $result);
    }

    /**
     * Test filtered tools with non-existent tool throws exception
     */
    public function testFilteredToolsWithNonExistent(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid values for parameter 'tools': nonexistent");

        $this->prompt->handle(context: 'all', tools: 'phpcs,nonexistent');
    }

    /**
     * Test all enabled tools summary
     */
    public function testAllEnabledToolsSummary(): void
    {
        Configure::write('Synapse.prompts.quality_tools.psalm.enabled', true);
        $prompt = new QualityAssurancePrompt();

        $result = $prompt->handle(context: 'all', tools: 'all');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

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
        $result = $this->prompt->handle(context: 'all', tools: 'phpcs');
        /** @phpstan-ignore-next-line */
        $text = $result[0]->content->text;

        $this->assertStringContainsString('`phpcs.xml`', $text);
        $this->assertStringNotContainsString('`phpstan.neon`', $text);
    }

    public function testInvalidContextThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'context': 'invalid'");

        $this->prompt->handle('invalid');
    }

    public function testInvalidContextContainsExpectedValues(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Expected one of: guidelines, integration, troubleshooting, all');

        $this->prompt->handle('setup');
    }

    public function testInvalidContextContainsPromptName(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Prompt: quality-assurance');

        $this->prompt->handle('bad');
    }

    public function testInvalidToolsThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid values for parameter 'tools': invalid");

        $this->prompt->handle('guidelines', 'phpcs,invalid');
    }

    public function testValidContextAndTools(): void
    {
        $result = $this->prompt->handle('integration', 'phpcs,phpstan');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
    }

    public function testDefaultContextAndToolsAreValid(): void
    {
        $result = $this->prompt->handle();

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
    }
}
