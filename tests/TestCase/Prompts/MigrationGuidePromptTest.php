<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enums\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $result = $this->prompt->render('4.5', '5.2', 'general');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('migration', (string)$content);
        $this->assertStringContainsString('breaking changes', (string)$content);
        $this->assertStringContainsString('4.5', (string)$content);
        $this->assertStringContainsString('5.2', (string)$content);
    }

    public function testSpecificArea(): void
    {
        $result = $this->prompt->render('3.10', '5.0', 'authentication');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('authentication', (string)$content);
        $this->assertStringContainsString('deprecated', (string)$content);
        $this->assertStringContainsString('3.10', (string)$content);
        $this->assertStringContainsString('5.0', (string)$content);
    }

    public function testIncludesPhpVersion(): void
    {
        $result = $this->prompt->render('4.0', '5.2');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP', (string)$content);
        $this->assertStringContainsString('8.2+', (string)$content);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new MigrationGuidePrompt();

        $result = $prompt->render('4.5', '5.2');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP 8.3+', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('4.0', '5.0', 'ORM');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testEmptyFromVersionThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'fromVersion' cannot be empty");

        $this->prompt->render('', '5.2');
    }

    public function testEmptyToVersionThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Parameter 'toVersion' cannot be empty");

        $this->prompt->render('4.5', '');
    }

    public function testValidVersionsWithArea(): void
    {
        $result = $this->prompt->render('4.0', '5.0', 'orm');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.0', (string)$content);
        $this->assertStringContainsString('5.0', (string)$content);
        $this->assertStringContainsString('orm', (string)$content);
    }
}
