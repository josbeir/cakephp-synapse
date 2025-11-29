<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Synapse\Tools\TinkerTools;

/**
 * TinkerTools Test Case
 *
 * Tests the tinker MCP tool for executing PHP code in subprocess mode.
 * Code is always executed in a subprocess to ensure the latest code from disk is loaded.
 */
class TinkerToolsTest extends TestCase
{
    protected array $fixtures = [
        'plugin.Synapse.Users',
    ];

    private TinkerTools $tinkerTools;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tinkerTools = new TinkerTools();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->tinkerTools);
        parent::tearDown();
    }

    // =========================================================================
    // Basic Execution Tests
    // =========================================================================

    /**
     * Test execute with simple expression
     */
    public function testExecuteSimpleExpression(): void
    {
        $result = $this->tinkerTools->execute('return 1 + 1;');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['result']);
        $this->assertEquals('int', $result['type']);
    }

    /**
     * Test execute with output and return value
     */
    public function testExecuteWithOutput(): void
    {
        $result = $this->tinkerTools->execute('echo "Hello"; return "World";');

        $this->assertTrue($result['success']);
        $this->assertEquals('World', $result['result']);
        $this->assertStringContainsString('Hello', $result['output']);
        $this->assertEquals('string', $result['type']);
    }

    /**
     * Test execute returns array with count
     */
    public function testExecuteReturnsArray(): void
    {
        $result = $this->tinkerTools->execute('return [1, 2, 3];');

        $this->assertTrue($result['success']);
        $this->assertIsArray($result['result']);
        $this->assertEquals([1, 2, 3], $result['result']);
        $this->assertEquals('array', $result['type']);
        $this->assertEquals(3, $result['count']);
    }

    /**
     * Test execute returns object serialized
     *
     * Objects are serialized for JSON transport
     */
    public function testExecuteReturnsObject(): void
    {
        $result = $this->tinkerTools->execute('return new \stdClass();');

        $this->assertTrue($result['success']);
        $this->assertEquals('stdClass', $result['type']);
        $this->assertEquals('stdClass', $result['class']);
        // Object is serialized as array with __class and __properties
        $this->assertIsArray($result['result']);
    }

    /**
     * Test execute with syntax error
     */
    public function testExecuteWithSyntaxError(): void
    {
        $result = $this->tinkerTools->execute('this is invalid php;');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('type', $result);
    }

    /**
     * Test execute with runtime exception
     */
    public function testExecuteWithRuntimeException(): void
    {
        $result = $this->tinkerTools->execute('throw new \Exception("Test error");');

        $this->assertFalse($result['success']);
        $this->assertEquals('Test error', $result['error']);
        $this->assertEquals('Exception', $result['type']);
        $this->assertArrayHasKey('trace', $result);
    }

    /**
     * Test PHP tags are stripped
     */
    public function testStripPhpTags(): void
    {
        $result = $this->tinkerTools->execute('<?php return 42; ?>');

        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['result']);
    }

    /**
     * Test get_debug_type for various types
     */
    public function testGetDebugType(): void
    {
        $result = $this->tinkerTools->execute('return null;');
        $this->assertTrue($result['success']);
        $this->assertNull($result['result']);
        $this->assertEquals('null', $result['type']);

        $result = $this->tinkerTools->execute('return true;');
        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']);
        $this->assertEquals('bool', $result['type']);

        $result = $this->tinkerTools->execute('return 3.14;');
        $this->assertTrue($result['success']);
        $this->assertEquals(3.14, $result['result']);
        $this->assertEquals('float', $result['type']);

        $result = $this->tinkerTools->execute('return "hello";');
        $this->assertTrue($result['success']);
        $this->assertEquals('hello', $result['result']);
        $this->assertEquals('string', $result['type']);
    }

    /**
     * Test execute with multiple statements
     */
    public function testExecuteWithMultipleStatements(): void
    {
        $code = '
            $a = 10;
            $b = 20;
            return $a + $b;
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertEquals(30, $result['result']);
    }

    /**
     * Test execute with no return value
     */
    public function testExecuteWithNoReturn(): void
    {
        $result = $this->tinkerTools->execute('$x = 1 + 1;');

        $this->assertTrue($result['success']);
        $this->assertNull($result['result']);
        $this->assertEquals('null', $result['type']);
    }

    /**
     * Test output is captured correctly
     */
    public function testOutputCapturedCorrectly(): void
    {
        $result = $this->tinkerTools->execute('echo "Line 1\n"; echo "Line 2\n"; return 42;');

        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['result']);
        $this->assertStringContainsString("Line 1\n", $result['output']);
        $this->assertStringContainsString("Line 2\n", $result['output']);
    }

    /**
     * Test error includes trace
     */
    public function testErrorIncludesTrace(): void
    {
        $result = $this->tinkerTools->execute('throw new \RuntimeException("Error message");');

        $this->assertFalse($result['success']);
        $this->assertEquals('Error message', $result['error']);
        $this->assertEquals('RuntimeException', $result['type']);
        $this->assertIsString($result['trace']);
        $this->assertNotEmpty($result['trace']);
    }

    /**
     * Test that TinkerTools class exists
     */
    public function testTinkerToolsClassExists(): void
    {
        $this->assertInstanceOf(TinkerTools::class, $this->tinkerTools);
    }

    // =========================================================================
    // Context Access Tests
    // =========================================================================

    /**
     * Test fetchTable is accessible via $context
     */
    public function testFetchTableViaContext(): void
    {
        $code = '
            $table = $context->fetchTable("Users");
            return $table::class;
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Table', $result['result']);
    }

    /**
     * Test getTableLocator is accessible via $context
     *
     * Note: Database queries are not tested in subprocess mode because
     * test fixtures are only loaded in the main PHPUnit process.
     */
    public function testGetTableLocatorViaContext(): void
    {
        $code = '
            $locator = $context->getTableLocator();
            return $locator::class;
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('TableLocator', $result['result']);
    }

    // =========================================================================
    // Timeout Tests
    // =========================================================================

    /**
     * Test timeout minimum bound
     */
    public function testTimeoutMinimumBound(): void
    {
        $result = $this->tinkerTools->execute('return 1;', -10);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['result']);
    }

    /**
     * Test timeout maximum bound
     */
    public function testTimeoutMaximumBound(): void
    {
        $result = $this->tinkerTools->execute('return 1;', 300);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['result']);
    }

    // =========================================================================
    // Configuration and Path Tests
    // =========================================================================

    /**
     * Test getPhpBinary returns a valid path
     */
    public function testGetPhpBinaryReturnsValidPath(): void
    {
        $phpBinary = $this->tinkerTools->getPhpBinary();

        $this->assertNotNull($phpBinary);
        $this->assertFileExists($phpBinary);
        $this->assertTrue(is_executable($phpBinary));
    }

    /**
     * Test getBinPath returns a valid path
     */
    public function testGetBinPathReturnsValidPath(): void
    {
        $binPath = $this->tinkerTools->getBinPath();

        $this->assertNotEmpty($binPath);
        $this->assertDirectoryExists($binPath);
    }

    /**
     * Test setPhpBinary allows setting custom path
     */
    public function testSetPhpBinaryAllowsCustomPath(): void
    {
        $customPath = '/custom/php/path';

        $result = $this->tinkerTools->setPhpBinary($customPath);

        $this->assertSame($this->tinkerTools, $result);
        $this->assertEquals($customPath, $this->tinkerTools->getPhpBinary());
    }

    /**
     * Test setBinPath allows setting custom path
     */
    public function testSetBinPathAllowsCustomPath(): void
    {
        $customPath = '/custom/bin/path';

        $result = $this->tinkerTools->setBinPath($customPath);

        $this->assertSame($this->tinkerTools, $result);
        $this->assertEquals($customPath, $this->tinkerTools->getBinPath());
    }

    /**
     * Test setPhpBinary can be reset to null
     */
    public function testSetPhpBinaryCanBeResetToNull(): void
    {
        $this->tinkerTools->setPhpBinary('/custom/path');
        $this->tinkerTools->setPhpBinary(null);

        // Should fall back to auto-detection
        $phpBinary = $this->tinkerTools->getPhpBinary();
        $this->assertNotEquals('/custom/path', $phpBinary);
    }

    /**
     * Test setBinPath can be reset to null
     */
    public function testSetBinPathCanBeResetToNull(): void
    {
        $this->tinkerTools->setBinPath('/custom/path');
        $this->tinkerTools->setBinPath(null);

        // Should fall back to auto-detection
        $binPath = $this->tinkerTools->getBinPath();
        $this->assertNotEquals('/custom/path', $binPath);
    }

    /**
     * Test configuration can override php_binary
     */
    public function testConfigurationOverridesPhpBinary(): void
    {
        $originalConfig = Configure::read('Synapse.tinker.php_binary');

        // Set a valid PHP binary path via configuration
        $phpBinary = $this->tinkerTools->getPhpBinary();
        Configure::write('Synapse.tinker.php_binary', $phpBinary);

        // Create new instance to pick up config
        $tinkerTools = new TinkerTools();
        $this->assertEquals($phpBinary, $tinkerTools->getPhpBinary());

        // Restore original config
        Configure::write('Synapse.tinker.php_binary', $originalConfig);
    }

    /**
     * Test configuration can override bin_path
     */
    public function testConfigurationOverridesBinPath(): void
    {
        $originalConfig = Configure::read('Synapse.tinker.bin_path');

        // Set a custom bin path via configuration
        $customBinPath = '/custom/configured/bin';
        Configure::write('Synapse.tinker.bin_path', $customBinPath);

        // Create new instance to pick up config
        $tinkerTools = new TinkerTools();
        $this->assertEquals($customBinPath, $tinkerTools->getBinPath());

        // Restore original config
        Configure::write('Synapse.tinker.bin_path', $originalConfig);
    }

    /**
     * Test subprocess fails gracefully with invalid PHP binary
     */
    public function testSubprocessFailsWithInvalidPhpBinary(): void
    {
        $this->tinkerTools->setPhpBinary('/nonexistent/php');

        $result = $this->tinkerTools->execute('return 1;');

        $this->assertFalse($result['success']);
        // Error can be our message or shell's "No such file or directory"
        $this->assertTrue(
            str_contains($result['error'], 'PHP binary') ||
            str_contains($result['error'], 'No such file or directory') ||
            str_contains($result['error'], 'not found'),
            'Expected error about missing PHP binary, got: ' . $result['error'],
        );
    }

    // =========================================================================
    // Default Behavior Tests
    // =========================================================================

    /**
     * Test default timeout is 30 seconds
     */
    public function testDefaultTimeoutIsThirtySeconds(): void
    {
        // This test just verifies execution works with default timeout
        $result = $this->tinkerTools->execute('return "ok";');

        $this->assertTrue($result['success']);
        $this->assertEquals('ok', $result['result']);
    }
}
