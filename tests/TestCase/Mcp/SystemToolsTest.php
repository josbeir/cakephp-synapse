<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Mcp;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Synapse\Mcp\SystemTools;

/**
 * SystemTools Test Case
 *
 * Tests the default system MCP tools.
 */
class SystemToolsTest extends TestCase
{
    private SystemTools $systemTools;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->systemTools = new SystemTools();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->systemTools);
        parent::tearDown();
    }

    /**
     * Test getSystemInfo method
     */
    public function testGetSystemInfo(): void
    {
        $result = $this->systemTools->getSystemInfo();

        $this->assertArrayHasKey('app_name', $result);
        $this->assertArrayHasKey('cakephp_version', $result);
        $this->assertArrayHasKey('php_version', $result);
        $this->assertArrayHasKey('debug_mode', $result);
        $this->assertArrayHasKey('timezone', $result);
        $this->assertArrayHasKey('encoding', $result);

        $this->assertEquals(PHP_VERSION, $result['php_version']);
        $this->assertIsString($result['cakephp_version']);
        $this->assertIsBool($result['debug_mode']);
    }

    /**
     * Test readConfig method with existing key
     */
    public function testReadConfigWithExistingKey(): void
    {
        Configure::write('TestKey', 'TestValue');

        $result = $this->systemTools->readConfig('TestKey');

        $this->assertEquals('TestValue', $result);

        Configure::delete('TestKey');
    }

    /**
     * Test readConfig method with non-existing key
     */
    public function testReadConfigWithNonExistingKey(): void
    {
        $result = $this->systemTools->readConfig('NonExistentKey.DoesNotExist');

        $this->assertNull($result);
    }

    /**
     * Test readConfig method with nested key
     */
    public function testReadConfigWithNestedKey(): void
    {
        Configure::write('Test.Nested.Key', 'NestedValue');

        $result = $this->systemTools->readConfig('Test.Nested.Key');

        $this->assertEquals('NestedValue', $result);

        Configure::delete('Test');
    }

    /**
     * Test getDebugStatus method
     */
    public function testGetDebugStatus(): void
    {
        $result = $this->systemTools->getDebugStatus();

        $this->assertArrayHasKey('debug', $result);
        $this->assertIsBool($result['debug']);

        // Only check for environment if APP_ENV is set
        $env = getenv('APP_ENV');
        if ($env !== false) {
            $this->assertArrayHasKey('environment', $result);
            $this->assertIsString($result['environment']);
        } else {
            $this->assertArrayNotHasKey('environment', $result);
        }
    }

    /**
     * Test getDebugStatus returns correct debug value
     */
    public function testGetDebugStatusReturnsCorrectDebugValue(): void
    {
        $originalDebug = Configure::read('debug');
        Configure::write('debug', true);

        $result = $this->systemTools->getDebugStatus();
        $this->assertTrue($result['debug']);

        Configure::write('debug', false);
        $result = $this->systemTools->getDebugStatus();
        $this->assertFalse($result['debug']);

        Configure::write('debug', $originalDebug);
    }

    /**
     * Test that SystemTools class exists
     */
    public function testSystemToolsClassExists(): void
    {
        $this->assertInstanceOf(SystemTools::class, $this->systemTools);
    }

    /**
     * Test listEnvVars method
     */
    public function testListEnvVars(): void
    {
        // Set a test environment variable
        putenv('TEST_ENV_VAR=test_value');

        $result = $this->systemTools->listEnvVars();

        $this->assertArrayHasKey('TEST_ENV_VAR', $result);
        $this->assertEquals('test_value', $result['TEST_ENV_VAR']);

        // Clean up
        putenv('TEST_ENV_VAR');
    }
}
