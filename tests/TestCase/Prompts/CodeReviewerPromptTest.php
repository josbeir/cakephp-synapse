<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;
use Synapse\Prompts\CodeReviewerPrompt;

/**
 * CodeReviewerPromptTest
 *
 * Tests for CodeReviewerPrompt
 */
class CodeReviewerPromptTest extends TestCase
{
    private CodeReviewerPrompt $prompt;

    private string $originalCakephpVersion;

    private string $originalPhpVersion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '5.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.2');
        $this->prompt = new CodeReviewerPrompt();
    }

    protected function tearDown(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', $this->originalCakephpVersion);
        Configure::write('Synapse.prompts.php_version', $this->originalPhpVersion);
        parent::tearDown();
    }

    public function testAll(): void
    {
        $code = 'public function index() { return $this->render(); }';
        $result = $this->prompt->handle($code, 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString($code, $content->text);
        $this->assertStringContainsStringIgnoringCase('conventions', $content->text);
        $this->assertStringContainsStringIgnoringCase('security', $content->text);
        $this->assertStringContainsStringIgnoringCase('performance', $content->text);
    }

    public function testConventionsFocus(): void
    {
        $code = 'class MyController {}';
        $result = $this->prompt->handle($code, 'conventions');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('5.x', $content->text);
        $this->assertStringContainsString($code, $content->text);
    }

    public function testSecurityFocus(): void
    {
        $code = '$query = "SELECT * FROM users WHERE id = " . $id;';
        $result = $this->prompt->handle($code, 'security');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('SQL injection', $content->text);
        $this->assertStringContainsString($code, $content->text);
    }

    public function testPerformanceFocus(): void
    {
        $code = 'foreach ($users as $user) { $user->posts; }';
        $result = $this->prompt->handle($code, 'performance');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('N+1', $content->text);
        $this->assertStringContainsString($code, $content->text);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new CodeReviewerPrompt();

        $result = $prompt->handle('<?php echo $var; ?>');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('4.5', $content->text);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new CodeReviewerPrompt();

        $result = $prompt->handle('public function test() {}');

        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertStringContainsString('PHP 8.3+', $content->text);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->handle('$x = 1;', 'all');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PromptMessage::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role);
        $this->assertInstanceOf(TextContent::class, $result[0]->content);
        /** @var TextContent $content */
        $content = $result[0]->content;
        $this->assertNotEmpty($content->text);
    }
}
