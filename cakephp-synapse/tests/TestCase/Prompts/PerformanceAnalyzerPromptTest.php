<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\PerformanceAnalyzerPrompt;

/**
 * PerformanceAnalyzerPromptTest
 *
 * Tests for PerformanceAnalyzerPrompt
 */
class PerformanceAnalyzerPromptTest extends TestCase
{
    private PerformanceAnalyzerPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->prompt = new PerformanceAnalyzerPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testBasic(): void
    {
        $result = $this->prompt->handle('slow database queries');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('performance', $content->text);
        $this->assertStringContainsString('optimization', $content->text);
        $this->assertStringContainsString('slow database queries', $content->text);
    }

    public function testWithContext(): void
    {
        $context = 'foreach loop over 10k records';
        $result = $this->prompt->handle('memory usage', $context);

        $this->assertIsArray($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString($context, $content->text);
        $this->assertStringContainsString('memory usage', $content->text);
    }

    public function testIncludesCachingGuidance(): void
    {
        $result = $this->prompt->handle('page load time');

        $this->assertIsArray($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('caching', $content->text);
        $this->assertStringContainsString('query cache', $content->text);
    }

    public function testIncludesQueryOptimization(): void
    {
        $result = $this->prompt->handle('slow queries');

        $this->assertIsArray($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('eager loading', $content->text);
        $this->assertStringContainsString('N+1', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new PerformanceAnalyzerPrompt();

        $result = $prompt->handle('performance issue');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('high CPU usage', 'complex query loop');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }
}
