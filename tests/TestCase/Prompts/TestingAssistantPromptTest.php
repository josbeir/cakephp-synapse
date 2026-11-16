<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $result = $this->prompt->render('UsersController::add()', 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('test', (string)$content);
        $this->assertStringContainsString('PHPUnit', (string)$content);
        $this->assertStringContainsString('UsersController::add()', (string)$content);
    }

    public function testUnit(): void
    {
        $result = $this->prompt->render('validate email', 'unit');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('unit', (string)$content);
        $this->assertStringContainsString('test', (string)$content);
        $this->assertStringContainsString('validate email', (string)$content);
    }

    public function testIntegration(): void
    {
        $result = $this->prompt->render('API endpoint', 'integration');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('integration', (string)$content);
        $this->assertStringContainsString('API endpoint', (string)$content);
    }

    public function testFixture(): void
    {
        $result = $this->prompt->render('Users table', 'fixture');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('fixture', (string)$content);
        $this->assertStringContainsString('Fixture definitions', (string)$content);
        $this->assertStringContainsString('Users table', (string)$content);
    }

    public function testIncludesTinker(): void
    {
        $result = $this->prompt->render('Some feature');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('tinker', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new TestingAssistantPrompt();

        $result = $prompt->render('test feature');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('authorization logic', 'unit');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidTestTypeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'testType': 'invalid'");

        $this->prompt->render('authentication logic', 'invalid');
    }

    public function testInvalidTestTypeContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: unit, integration, fixture, all');

        $this->prompt->render('payment processing', 'acceptance');
    }

    public function testInvalidTestTypeContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: testing-assistant');

        $this->prompt->render('user model', 'bad');
    }

    public function testDefaultTestTypeIsValid(): void
    {
        $result = $this->prompt->render('database queries');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
