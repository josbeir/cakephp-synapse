<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $result = $this->prompt->render('slow database queries');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());

        $content = $result[0]->content();
        $this->assertStringContainsString('performance', (string)$content);
        $this->assertStringContainsString('optimization', (string)$content);
        $this->assertStringContainsString('slow database queries', (string)$content);
    }

    public function testWithContext(): void
    {
        $context = 'foreach loop over 10k records';
        $result = $this->prompt->render('memory usage', $context);

        $content = $result[0]->content();
        $this->assertStringContainsString($context, (string)$content);
        $this->assertStringContainsString('memory usage', (string)$content);
    }

    public function testIncludesCachingGuidance(): void
    {
        $result = $this->prompt->render('page load time');

        $content = $result[0]->content();
        $this->assertStringContainsString('caching', (string)$content);
        $this->assertStringContainsString('query cache', (string)$content);
    }

    public function testIncludesQueryOptimization(): void
    {
        $result = $this->prompt->render('slow queries');

        $content = $result[0]->content();
        $this->assertStringContainsString('eager loading', (string)$content);
        $this->assertStringContainsString('N+1', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new PerformanceAnalyzerPrompt();

        $result = $prompt->render('performance issue');

        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('high CPU usage', 'complex query loop');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());

        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testEmptyConcernThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'concern' cannot be empty");

        $this->prompt->render('');
    }

    public function testEmptyConcernWithContextThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'concern' cannot be empty");

        $this->prompt->render('', 'some code snippet');
    }

    public function testValidConcernWithContext(): void
    {
        $result = $this->prompt->render('high memory usage', 'loop processing');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('high memory usage', (string)$content);
        $this->assertStringContainsString('loop processing', (string)$content);
    }
}
