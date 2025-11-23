<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\TestingAssistantPrompt;

/**
 * TestingAssistantPromptTest
 *
 * Tests for TestingAssistantPrompt
 */
class TestingAssistantPromptTest extends TestCase
{
    private TestingAssistantPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->prompt = new TestingAssistantPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testAll(): void
    {
        $result = $this->prompt->handle('UsersController::add()', 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('test', $content->text);
        $this->assertStringContainsString('PHPUnit', $content->text);
        $this->assertStringContainsString('UsersController::add()', $content->text);
    }

    public function testUnit(): void
    {
        $result = $this->prompt->handle('validate email', 'unit');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('unit', $content->text);
        $this->assertStringContainsString('test', $content->text);
        $this->assertStringContainsString('validate email', $content->text);
    }

    public function testIntegration(): void
    {
        $result = $this->prompt->handle('API endpoint', 'integration');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('integration', $content->text);
        $this->assertStringContainsString('API endpoint', $content->text);
    }

    public function testFixture(): void
    {
        $result = $this->prompt->handle('Users table', 'fixture');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('fixture', $content->text);
        $this->assertStringContainsString('Fixture definitions', $content->text);
        $this->assertStringContainsString('Users table', $content->text);
    }

    public function testIncludesTinker(): void
    {
        $result = $this->prompt->handle('Some feature');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('tinker', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new TestingAssistantPrompt();

        $result = $prompt->handle('test feature');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('authorization logic', 'unit');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }

    public function testInvalidTestTypeThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'testType': 'invalid'");

        $this->prompt->handle('authentication logic', 'invalid');
    }

    public function testInvalidTestTypeContainsExpectedValues(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Expected one of: unit, integration, fixture, all');

        $this->prompt->handle('payment processing', 'acceptance');
    }

    public function testInvalidTestTypeContainsPromptName(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Prompt: testing-assistant');

        $this->prompt->handle('user model', 'bad');
    }

    public function testDefaultTestTypeIsValid(): void
    {
        $result = $this->prompt->handle('database queries');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
    }
}
