<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
use Synapse\Prompts\TinkerWorkshopPrompt;

/**
 * TinkerWorkshopPromptTest
 *
 * Tests for TinkerWorkshopPrompt
 */
class TinkerWorkshopPromptTest extends TestCase
{
    private TinkerWorkshopPrompt $prompt;

    private string $originalCakephpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->prompt = new TinkerWorkshopPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        parent::tearDown();
    }

    public function testExplore(): void
    {
        $result = $this->prompt->render('explore', 'Authentication component');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('explore', (string)$content);
        $this->assertStringContainsString('fetchTable', (string)$content);
        $this->assertStringContainsString('Authentication component', (string)$content);
    }

    public function testTest(): void
    {
        $result = $this->prompt->render('test', 'validation rules');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('test', (string)$content);
        $this->assertStringContainsString('validation rules', (string)$content);
    }

    public function testDebug(): void
    {
        $result = $this->prompt->render('debug', 'query issue');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('debug', (string)$content);
        $this->assertStringContainsString('diagnostic', (string)$content);
        $this->assertStringContainsString('query issue', (string)$content);
    }

    public function testWithoutSubject(): void
    {
        $result = $this->prompt->render('explore');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
    }

    public function testIncludesAvailableMethods(): void
    {
        $result = $this->prompt->render('test');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('fetchTable()', (string)$content);
        $this->assertStringContainsString('getTableLocator()', (string)$content);
        $this->assertStringContainsString('log()', (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new TinkerWorkshopPrompt();

        $result = $prompt->render('explore', 'table structure');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('debug', 'association loading');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidGoalThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'goal': 'invalid'");

        $this->prompt->render('invalid', 'some subject');
    }

    public function testInvalidGoalContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: explore, test, debug');

        $this->prompt->render('analyze', 'database queries');
    }

    public function testInvalidGoalContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: tinker-workshop');

        $this->prompt->render('bad', 'something');
    }

    public function testValidGoalsWithoutSubject(): void
    {
        $result = $this->prompt->render('explore');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
