<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
use Synapse\Prompts\DocumentationExpertPrompt;

/**
 * DocumentationExpertPromptTest
 *
 * Tests for DocumentationExpertPrompt
 */
class DocumentationExpertPromptTest extends TestCase
{
    private DocumentationExpertPrompt $prompt;

    private string $originalCakephpVersion;

    private string $originalPhpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.5');
        $this->prompt = new DocumentationExpertPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        Configure::write('Synapse.prompts.php_version', $this->originalPhpVersion);
        parent::tearDown();
    }

    public function testBasicDepth(): void
    {
        $result = $this->prompt->render('Authentication', 'basic');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('basic overview', (string)$content);
        $this->assertStringContainsString('Authentication', (string)$content);
        $this->assertStringContainsString('6.x', (string)$content);
    }

    public function testIntermediateDepth(): void
    {
        $result = $this->prompt->render('ORM', 'intermediate');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('detailed information', (string)$content);
        $this->assertStringContainsString('ORM', (string)$content);
    }

    public function testAdvancedDepth(): void
    {
        $result = $this->prompt->render('Middleware', 'advanced');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('deep dive', (string)$content);
        $this->assertStringContainsString('expert-level', (string)$content);
    }

    public function testDefaultsToIntermediate(): void
    {
        $result = $this->prompt->render('Controllers');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('detailed information', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new DocumentationExpertPrompt();

        $result = $prompt->render('Routing', 'basic');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
        $this->assertStringNotContainsString('6.x', (string)$content);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new DocumentationExpertPrompt();

        $result = $prompt->render('Types', 'intermediate');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP 8.3+', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('Testing', 'basic');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidDepthThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'depth': 'expert'");

        $this->prompt->render('Authentication', 'expert');
    }

    public function testInvalidDepthContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: basic, intermediate, advanced');

        $this->prompt->render('ORM', 'invalid');
    }

    public function testInvalidDepthContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: documentation-expert');

        $this->prompt->render('Controllers', 'custom');
    }
}
