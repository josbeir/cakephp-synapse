<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
use Synapse\Prompts\DebugHelperPrompt;

/**
 * DebugHelperPromptTest
 *
 * Tests for DebugHelperPrompt
 */
class DebugHelperPromptTest extends TestCase
{
    private DebugHelperPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->prompt = new DebugHelperPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testBasicError(): void
    {
        $error = 'Call to undefined method Foo::bar()';
        $result = $this->prompt->render($error);

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString($error, (string)$content);
        $this->assertStringContainsString('diagnostic steps', (string)$content);
        $this->assertStringContainsString('search_docs', (string)$content);
    }

    public function testWithContext(): void
    {
        $error = 'Database connection failed';
        $result = $this->prompt->render($error, 'database');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('database layer', (string)$content);
        $this->assertStringContainsString($error, (string)$content);
    }

    public function testIncludesVersionReference(): void
    {
        $result = $this->prompt->render('Some error', 'controller');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('6.x', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new DebugHelperPrompt();

        $result = $prompt->render('Error message');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('Test error');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidContextThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'context': 'invalid'");

        $this->prompt->render('Some error', 'invalid');
    }

    public function testInvalidContextContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: controller, model, database, view');

        $this->prompt->render('Error message', 'service');
    }

    public function testInvalidContextContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: debug-helper');

        $this->prompt->render('Test error', 'bad');
    }

    public function testEmptyContextIsValid(): void
    {
        $result = $this->prompt->render('Error message', '');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
