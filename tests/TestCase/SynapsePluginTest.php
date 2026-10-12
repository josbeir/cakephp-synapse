<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase;

use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\TestSuite\TestCase;
use Mcp\Schema\Content\TextContent;
use Synapse\Prompts\FeatureBuilderPrompt;
use Synapse\SynapsePlugin;

/**
 * Tests the CakePHP 6 configuration exposed to MCP clients.
 */
class SynapsePluginTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $originalConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConfig = Configure::read('Synapse');
    }

    protected function tearDown(): void
    {
        Configure::delete('Synapse');
        if ($this->originalConfig !== null) {
            Configure::write('Synapse', $this->originalConfig);
        }

        parent::tearDown();
    }

    public function testBootstrapDefaultsTargetCakephp6(): void
    {
        (new SynapsePlugin())->bootstrap($this->createStub(PluginApplicationInterface::class));

        $source = Configure::read('Synapse.documentation.sources.cakephp-6x');
        $this->assertIsArray($source);
        $this->assertTrue($source['enabled']);
        $this->assertSame('6.x', $source['branch']);
        $this->assertSame('docs/en', $source['root']);
        $this->assertSame('6.x', $source['metadata']['version']);

        $messages = (new FeatureBuilderPrompt())->handle('a CakePHP command', 'command');
        $this->assertInstanceOf(TextContent::class, $messages[0]->content);
        $this->assertStringContainsString('CakePHP 6.x', $messages[0]->content->text);
        $this->assertStringContainsString('PHP 8.5+', $messages[0]->content->text);
    }

    public function testPromptFallbackTargetsCakephp6(): void
    {
        Configure::delete('Synapse.prompts');

        $messages = (new FeatureBuilderPrompt())->handle('a CakePHP command', 'command');
        $this->assertInstanceOf(TextContent::class, $messages[0]->content);
        $this->assertStringContainsString('CakePHP 6.x', $messages[0]->content->text);
        $this->assertStringContainsString('PHP 8.5+', $messages[0]->content->text);
    }
}
