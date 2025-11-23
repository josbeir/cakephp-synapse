<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
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
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.2');
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
        $result = $this->prompt->handle('Authentication', 'basic');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('basic overview', $content->text);
        $this->assertStringContainsString('Authentication', $content->text);
        $this->assertStringContainsString('5.x', $content->text);
    }

    public function testIntermediateDepth(): void
    {
        $result = $this->prompt->handle('ORM', 'intermediate');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('detailed information', $content->text);
        $this->assertStringContainsString('ORM', $content->text);
    }

    public function testAdvancedDepth(): void
    {
        $result = $this->prompt->handle('Middleware', 'advanced');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('deep dive', $content->text);
        $this->assertStringContainsString('expert-level', $content->text);
    }

    public function testDefaultsToIntermediate(): void
    {
        $result = $this->prompt->handle('Controllers');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('detailed information', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new DocumentationExpertPrompt();

        $result = $prompt->handle('Routing', 'basic');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
        $this->assertStringNotContainsString('5.x', $content->text);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new DocumentationExpertPrompt();

        $result = $prompt->handle('Types', 'intermediate');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP 8.3+', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('Testing', 'basic');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }
}
