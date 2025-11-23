<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\OrmQueryHelperPrompt;

/**
 * OrmQueryHelperPromptTest
 *
 * Tests for OrmQueryHelperPrompt
 */
class OrmQueryHelperPromptTest extends TestCase
{
    private OrmQueryHelperPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->prompt = new OrmQueryHelperPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testBasic(): void
    {
        $result = $this->prompt->handle('find all users with their posts');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('ORM query', $content->text);
        $this->assertStringContainsString('find()', $content->text);
        $this->assertStringContainsString('find all users with their posts', $content->text);
    }

    public function testWithTables(): void
    {
        $result = $this->prompt->handle('join users and posts', 'users,posts');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('users,posts', $content->text);
        $this->assertStringContainsString('contain', $content->text);
        $this->assertStringContainsString('join users and posts', $content->text);
    }

    public function testIncludesTinkerExample(): void
    {
        $result = $this->prompt->handle('complex query');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('tinker', $content->text);
        $this->assertStringContainsString('fetchTable', $content->text);
        $this->assertStringContainsString('toArray()', $content->text);
    }

    public function testIncludesSqlReference(): void
    {
        $result = $this->prompt->handle('get recent records');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('sql()', $content->text);
        $this->assertStringContainsString('equivalent SQL', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new OrmQueryHelperPrompt();

        $result = $prompt->handle('query example');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('get active users', 'users');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }

    public function testEmptyQueryGoalThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Parameter 'queryGoal' cannot be empty");

        $this->prompt->handle('');
    }

    public function testEmptyQueryGoalWithTablesThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Parameter 'queryGoal' cannot be empty");

        $this->prompt->handle('', 'users,posts');
    }

    public function testValidQueryGoalWithTables(): void
    {
        $result = $this->prompt->handle('find active users', 'users,roles');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('find active users', $content->text);
        $this->assertStringContainsString('users,roles', $content->text);
    }
}
