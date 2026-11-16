<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Prompts;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Mcp\Enum\Role;
use Crustum\Mcp\Exception\ValidationException;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Content\Text as TextContent;
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
        $this->originalCakephpVersion = Configure::read('Synapse.prompts.cakephp_version', '6.x');
        $this->originalPhpVersion = Configure::read('Synapse.prompts.php_version', '8.5');
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
        $result = $this->prompt->render($code, 'all');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString($code, (string)$content);
        $this->assertStringContainsStringIgnoringCase('conventions', (string)$content);
        $this->assertStringContainsStringIgnoringCase('security', (string)$content);
        $this->assertStringContainsStringIgnoringCase('performance', (string)$content);
    }

    public function testConventionsFocus(): void
    {
        $code = 'class MyController {}';
        $result = $this->prompt->render($code, 'conventions');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('6.x', (string)$content);
        $this->assertStringContainsString($code, (string)$content);
    }

    public function testSecurityFocus(): void
    {
        $code = '$query = "SELECT * FROM users WHERE id = " . $id;';
        $result = $this->prompt->render($code, 'security');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('SQL injection', (string)$content);
        $this->assertStringContainsString($code, (string)$content);
    }

    public function testPerformanceFocus(): void
    {
        $code = 'foreach ($users as $user) { $user->posts; }';
        $result = $this->prompt->render($code, 'performance');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('N+1', (string)$content);
        $this->assertStringContainsString($code, (string)$content);
    }

    public function testUsesConfiguredCakephpVersion(): void
    {
        Configure::write('Synapse.prompts.cakephp_version', '4.5');
        $prompt = new CodeReviewerPrompt();

        $result = $prompt->render('<?php echo $var; ?>');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('4.5', (string)$content);
    }

    public function testUsesConfiguredPhpVersion(): void
    {
        Configure::write('Synapse.prompts.php_version', '8.3');
        $prompt = new CodeReviewerPrompt();

        $result = $prompt->render('public function test() {}');

        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertStringContainsString('PHP 8.3+', (string)$content);
    }

    public function testReturnsValidStructure(): void
    {
        $result = $this->prompt->render('$x = 1;', 'all');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Response::class, $result[0]);
        $this->assertSame(Role::User, $result[0]->role());
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
        $content = $result[0]->content();
        $this->assertNotEmpty((string)$content);
    }

    public function testInvalidFocusThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid value for parameter 'focus': 'invalid'");

        $this->prompt->render('public function test() {}', 'invalid');
    }

    public function testInvalidFocusContainsExpectedValues(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Expected one of: conventions, security, performance, testing, all');

        $this->prompt->render('$x = 1;', 'optimization');
    }

    public function testInvalidFocusContainsPromptName(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Prompt: code-reviewer');

        $this->prompt->render('function test() {}', 'bad');
    }

    public function testDefaultFocusIsValid(): void
    {
        $result = $this->prompt->render('return true;');

        $this->assertNotEmpty($result);
        $this->assertInstanceOf(TextContent::class, $result[0]->content());
    }
}
