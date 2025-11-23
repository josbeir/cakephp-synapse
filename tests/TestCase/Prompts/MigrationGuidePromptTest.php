<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Exception\PromptGetException;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\MigrationGuidePrompt;

/**
 * MigrationGuidePromptTest
 *
 * Tests for MigrationGuidePrompt
 */
class MigrationGuidePromptTest extends TestCase
{
    private MigrationGuidePrompt $prompt;

    private string $originalPhpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.2');
        $this->prompt = new MigrationGuidePrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.php_version', $this->originalPhpVersion);
        parent::tearDown();
    }

    public function testGeneral(): void
    {
        $result = $this->prompt->handle('4.5', '5.2', 'general');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('migration', $content->text);
        $this->assertStringContainsString('breaking changes', $content->text);
        $this->assertStringContainsString('4.5', $content->text);
        $this->assertStringContainsString('5.2', $content->text);
    }

    public function testSpecificArea(): void
    {
        $result = $this->prompt->handle('3.10', '5.0', 'authentication');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('authentication', $content->text);
        $this->assertStringContainsString('deprecated', $content->text);
        $this->assertStringContainsString('3.10', $content->text);
        $this->assertStringContainsString('5.0', $content->text);
    }

    public function testIncludesPhpVersion(): void
    {
        $result = $this->prompt->handle('4.0', '5.2');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP', $content->text);
        $this->assertStringContainsString('8.2+', $content->text);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new MigrationGuidePrompt();

        $result = $prompt->handle('4.5', '5.2');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP 8.3+', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('4.0', '5.0', 'ORM');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }

    public function testEmptyFromVersionThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Parameter 'fromVersion' cannot be empty");

        $this->prompt->handle('', '5.2');
    }

    public function testEmptyToVersionThrowsException(): void
    {
        $this->expectException(PromptGetException::class);
        $this->expectExceptionMessage("Parameter 'toVersion' cannot be empty");

        $this->prompt->handle('4.5', '');
    }

    public function testValidVersionsWithArea(): void
    {
        $result = $this->prompt->handle('4.0', '5.0', 'orm');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.0', $content->text);
        $this->assertStringContainsString('5.0', $content->text);
        $this->assertStringContainsString('orm', $content->text);
    }
}
