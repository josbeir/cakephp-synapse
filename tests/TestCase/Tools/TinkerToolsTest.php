<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\Log\Engine\ArrayLog;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use stdClass;
use Synapse\Tools\TinkerTools;

/**
 * TinkerTools Test Case
 *
 * Tests the tinker MCP tool for executing PHP code.
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

    /**
     * Test execute with simple expression
     */
    public function testExecuteSimpleExpression(): void
    {
        $result = $this->tinkerTools->execute('return 1 + 1;');

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['result']);
        $this->assertEquals('int', $result['type']);
        $this->assertNull($result['output']);
    }

    /**
     * Test execute with output and return value
     */
    public function testExecuteWithOutput(): void
    {
        $result = $this->tinkerTools->execute('echo "Hello"; return "World";');

        $this->assertTrue($result['success']);
        $this->assertEquals('World', $result['result']);
        $this->assertEquals('Hello', $result['output']);
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
     * Test execute returns object with class name
     */
    public function testExecuteReturnsObject(): void
    {
        $result = $this->tinkerTools->execute('return new \stdClass();');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(stdClass::class, $result['result']);
        $this->assertEquals('stdClass', $result['type']);
        $this->assertEquals('stdClass', $result['class']);
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
        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('line', $result);
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
     * Test PHP short tags are stripped
     */
    public function testStripPhpShortTags(): void
    {
        $result = $this->tinkerTools->execute('<? return 42; ?>');

        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['result']);
    }

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

    /**
     * Test get_debug_type for null
     */
    public function testGetDebugTypeForNull(): void
    {
        $result = $this->tinkerTools->execute('return null;');

        $this->assertTrue($result['success']);
        $this->assertNull($result['result']);
        $this->assertEquals('null', $result['type']);
    }

    /**
     * Test get_debug_type for bool
     */
    public function testGetDebugTypeForBool(): void
    {
        $result = $this->tinkerTools->execute('return true;');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['result']);
        $this->assertEquals('bool', $result['type']);
    }

    /**
     * Test get_debug_type for float
     */
    public function testGetDebugTypeForFloat(): void
    {
        $result = $this->tinkerTools->execute('return 3.14;');

        $this->assertTrue($result['success']);
        $this->assertEquals(3.14, $result['result']);
        $this->assertEquals('float', $result['type']);
    }

    /**
     * Test get_debug_type for string
     */
    public function testGetDebugTypeForString(): void
    {
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
        $this->assertEquals("Line 1\nLine 2\n", $result['output']);
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

    /**
     * Test fetchTable is accessible in eval context
     */
    public function testFetchTableInEvalContext(): void
    {
        $code = '
            $table = $this->fetchTable("Users");
            return $table::class;
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Table', $result['result']);
    }

    /**
     * Test can query using ORM in eval context
     */
    public function testQueryUsingOrmInEvalContext(): void
    {
        $code = '
            $table = $this->fetchTable("Users");
            return $table->find()->count();
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['result']);
        $this->assertEquals('int', $result['type']);
    }

    /**
     * Test log method is accessible in eval context
     */
    public function testLogInEvalContext(): void
    {
        // Configure test logger
        Log::drop('test_tinker');
        Log::setConfig('test_tinker', [
            'className' => 'Array',
            'levels' => ['debug', 'info'],
        ]);

        $code = '
            $this->log("Test log message", "debug");
            return "logged";
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertEquals('logged', $result['result']);

        // Verify log was written
        $logger = Log::engine('test_tinker');
        $this->assertInstanceOf(ArrayLog::class, $logger);
        $messages = $logger->read();
        $this->assertStringContainsString('Test log message', implode("\n", $messages));

        // Cleanup
        Log::drop('test_tinker');
    }

    /**
     * Test getTableLocator is accessible in eval context
     */
    public function testGetTableLocatorInEvalContext(): void
    {
        $code = '
            $locator = $this->getTableLocator();
            return $locator::class;
        ';
        $result = $this->tinkerTools->execute($code);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('TableLocator', $result['result']);
    }
}
