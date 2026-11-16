<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->prompt = new DatabaseExplorerPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testAll(): void
    {
        $result = $this->prompt->render('users', 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('fetchTable', (string)$content);
        $this->assertStringContainsString('users', (string)$content);
        $this->assertStringContainsString('getSchema', (string)$content);
    }

    public function testSchema(): void
    {
        $result = $this->prompt->render('posts', 'schema');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('schema', (string)$content);
        $this->assertStringContainsString('posts', (string)$content);
    }

    public function testData(): void
    {
        $result = $this->prompt->render('articles', 'data');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('data', (string)$content);
        $this->assertStringContainsString('find()->limit(5)', (string)$content);
    }

    public function testRelationships(): void
    {
        $result = $this->prompt->render('comments', 'relationships');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('relationships', (string)$content);
        $this->assertStringContainsString('associations', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new DatabaseExplorerPrompt();

        $result = $prompt->render('products');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('orders', 'schema');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testEmptyTableThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'table' cannot be empty");

        $this->prompt->render('');
    }

    public function testInvalidShowThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'show': 'invalid'");

        $this->prompt->render('users', 'invalid');
    }

    public function testInvalidShowContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: schema, data, relationships, all');

        $this->prompt->render('posts', 'query');
    }

    public function testInvalidShowContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: database-explorer');

        $this->prompt->render('articles', 'bad');
    }

    public function testDefaultShowIsValid(): void
    {
        $result = $this->prompt->render('products');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
