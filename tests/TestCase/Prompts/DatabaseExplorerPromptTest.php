<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\DatabaseExplorerPrompt;

/**
 * DatabaseExplorerPromptTest
 *
 * Tests for DatabaseExplorerPrompt
 */
class DatabaseExplorerPromptTest extends TestCase
{
    private DatabaseExplorerPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->prompt = new DatabaseExplorerPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testAll(): void
    {
        $result = $this->prompt->handle('users', 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('fetchTable', $content->text);
        $this->assertStringContainsString('users', $content->text);
        $this->assertStringContainsString('getSchema', $content->text);
    }

    public function testSchema(): void
    {
        $result = $this->prompt->handle('posts', 'schema');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('schema', $content->text);
        $this->assertStringContainsString('posts', $content->text);
    }

    public function testData(): void
    {
        $result = $this->prompt->handle('articles', 'data');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('data', $content->text);
        $this->assertStringContainsString('find()->limit(5)', $content->text);
    }

    public function testRelationships(): void
    {
        $result = $this->prompt->handle('comments', 'relationships');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('relationships', $content->text);
        $this->assertStringContainsString('associations', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new DatabaseExplorerPrompt();

        $result = $prompt->handle('products');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('orders', 'schema');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }

    public function testEmptyTableThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Parameter 'table' cannot be empty");

        $this->prompt->handle('');
    }

    public function testInvalidShowThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'show': 'invalid'");

        $this->prompt->handle('users', 'invalid');
    }

    public function testInvalidShowContainsExpectedValues(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Expected one of: schema, data, relationships, all');

        $this->prompt->handle('posts', 'query');
    }

    public function testInvalidShowContainsPromptName(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage('Prompt: database-explorer');

        $this->prompt->handle('articles', 'bad');
    }

    public function testDefaultShowIsValid(): void
    {
        $result = $this->prompt->handle('products');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
    }
}
