<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\FeatureBuilderPrompt;

/**
 * FeatureBuilderPromptTest
 *
 * Tests for FeatureBuilderPrompt
 */
class FeatureBuilderPromptTest extends TestCase
{
    private FeatureBuilderPrompt $prompt;

    private string $originalCakephpVersion;

    private string $originalPhpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.2');
        $this->prompt = new FeatureBuilderPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        Configure::write('Synapse.prompts.php_version', $this->originalPhpVersion);
        parent::tearDown();
    }

    public function testFullStack(): void
    {
        $result = $this->prompt->handle('user authentication', 'full-stack');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('full-stack', $content->text);
        $this->assertStringContainsString('controllers, models, views', $content->text);
        $this->assertStringContainsString('5.x', $content->text);
    }

    public function testSpecificComponent(): void
    {
        $result = $this->prompt->handle('file upload', 'controller');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('controller', $content->text);
        $this->assertStringContainsString('file upload', $content->text);
    }

    public function testIncludesPhpVersion(): void
    {
        $result = $this->prompt->handle('REST API endpoint', 'middleware');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP 8.2+', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new FeatureBuilderPrompt();

        $result = $prompt->handle('payment integration');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new FeatureBuilderPrompt();

        $result = $prompt->handle('API endpoint');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP 8.3+', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('export feature', 'model');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }

    public function testInvalidComponentThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'component': 'invalid'");

        $this->prompt->handle('user authentication', 'invalid');
    }

    public function testInvalidComponentContainsExpectedValues(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Expected one of: controller, model, behavior, helper, middleware, command, full-stack');

        $this->prompt->handle('feature', 'service');
    }

    public function testInvalidComponentContainsPromptName(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Prompt: feature-builder');

        $this->prompt->handle('REST API', 'bad');
    }

    public function testDefaultComponentIsValid(): void
    {
        $result = $this->prompt->handle('payment integration');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
    }
}
