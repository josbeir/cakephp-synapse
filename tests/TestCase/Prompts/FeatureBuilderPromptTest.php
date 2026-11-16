<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.5');
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
        $result = $this->prompt->render('user authentication', 'full-stack');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('full-stack', (string)$content);
        $this->assertStringContainsString('controllers, models, views', (string)$content);
        $this->assertStringContainsString('6.x', (string)$content);
    }

    public function testSpecificComponent(): void
    {
        $result = $this->prompt->render('file upload', 'controller');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('controller', (string)$content);
        $this->assertStringContainsString('file upload', (string)$content);
    }

    public function testIncludesPhpVersion(): void
    {
        $result = $this->prompt->render('REST API endpoint', 'middleware');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP 8.5+', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new FeatureBuilderPrompt();

        $result = $prompt->render('payment integration');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new FeatureBuilderPrompt();

        $result = $prompt->render('API endpoint');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP 8.3+', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('export feature', 'model');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidComponentThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'component': 'invalid'");

        $this->prompt->render('user authentication', 'invalid');
    }

    public function testInvalidComponentContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: controller, model, behavior, helper, middleware, command, full-stack');

        $this->prompt->render('feature', 'service');
    }

    public function testInvalidComponentContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: feature-builder');

        $this->prompt->render('REST API', 'bad');
    }

    public function testDefaultComponentIsValid(): void
    {
        $result = $this->prompt->render('payment integration');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
