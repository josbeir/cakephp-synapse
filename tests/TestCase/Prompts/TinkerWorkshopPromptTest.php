<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
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
        $result = $this->prompt->handle('explore', 'Authentication component');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('explore', $content->text);
        $this->assertStringContainsString('fetchTable', $content->text);
        $this->assertStringContainsString('Authentication component', $content->text);
    }

    public function testTest(): void
    {
        $result = $this->prompt->handle('test', 'validation rules');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('test', $content->text);
        $this->assertStringContainsString('validation rules', $content->text);
    }

    public function testDebug(): void
    {
        $result = $this->prompt->handle('debug', 'query issue');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('debug', $content->text);
        $this->assertStringContainsString('diagnostic', $content->text);
        $this->assertStringContainsString('query issue', $content->text);
    }

    public function testWithoutSubject(): void
    {
        $result = $this->prompt->handle('explore');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
    }

    public function testIncludesAvailableMethods(): void
    {
        $result = $this->prompt->handle('test');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('fetchTable()', $content->text);
        $this->assertStringContainsString('getTableLocator()', $content->text);
        $this->assertStringContainsString('log()', $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new TinkerWorkshopPrompt();

        $result = $prompt->handle('explore', 'table structure');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('debug', 'association loading');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }
}
