<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $result = $this->prompt->render('find all users with their posts');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('ORM query', (string)$content);
        $this->assertStringContainsString('find()', (string)$content);
        $this->assertStringContainsString('find all users with their posts', (string)$content);
    }

    public function testWithTables(): void
    {
        $result = $this->prompt->render('join users and posts', 'users,posts');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('users,posts', (string)$content);
        $this->assertStringContainsString('contain', (string)$content);
        $this->assertStringContainsString('join users and posts', (string)$content);
    }

    public function testIncludesTinkerExample(): void
    {
        $result = $this->prompt->render('complex query');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('tinker', (string)$content);
        $this->assertStringContainsString('fetchTable', (string)$content);
        $this->assertStringContainsString('toArray()', (string)$content);
    }

    public function testIncludesSqlReference(): void
    {
        $result = $this->prompt->render('get recent records');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('sql()', (string)$content);
        $this->assertStringContainsString('equivalent SQL', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new OrmQueryHelperPrompt();

        $result = $prompt->render('query example');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('get active users', 'users');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testEmptyQueryGoalThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'queryGoal' cannot be empty");

        $this->prompt->render('');
    }

    public function testEmptyQueryGoalWithTablesThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'queryGoal' cannot be empty");

        $this->prompt->render('', 'users,posts');
    }

    public function testValidQueryGoalWithTables(): void
    {
        $result = $this->prompt->render('find active users', 'users,roles');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('find active users', (string)$content);
        $this->assertStringContainsString('users,roles', (string)$content);
    }
}
