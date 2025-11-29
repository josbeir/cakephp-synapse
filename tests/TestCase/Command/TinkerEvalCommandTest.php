<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * TinkerEvalCommand Test Case
 *
 * Tests the synapse tinker_eval command used for subprocess PHP code execution.
 */
class TinkerEvalCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = [
        'plugin.Synapse.Users',
    ];

    /**
     * Test command name
     */
    public function testCommandName(): void
    {
        $this->exec('synapse tinker_eval --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Execute PHP code');
    }

    /**
     * Test execution with simple expression via stdin simulation
     */
    public function testExecuteSimpleExpression(): void
    {
        // Use process to test stdin functionality
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'return 1 + 1;');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(0, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['result']);
        $this->assertEquals('int', $result['type']);
    }

    /**
     * Test execution with array return value
     */
    public function testExecuteReturnsArray(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'return [1, 2, 3];');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(0, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals([1, 2, 3], $result['result']);
        $this->assertEquals('array', $result['type']);
        $this->assertEquals(3, $result['count']);
    }

    /**
     * Test execution with output capture
     */
    public function testExecuteWithOutput(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'echo "Hello World"; return "done";');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('done', $result['result']);
        $this->assertStringContainsString('Hello World', $result['output']);
    }

    /**
     * Test execution with context access
     */
    public function testExecuteWithContextAccess(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], '$table = $this->fetchTable("Users"); return $table::class;');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Table', $result['result']);
    }

    /**
     * Test execution with exception
     */
    public function testExecuteWithException(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'throw new \Exception("Test error");');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(1, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('Test error', $result['error']);
        $this->assertEquals('Exception', $result['type']);
        $this->assertArrayHasKey('trace', $result);
    }

    /**
     * Test execution with syntax error
     */
    public function testExecuteWithSyntaxError(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'invalid php code here;');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(1, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    /**
     * Test execution with no input returns error
     */
    public function testExecuteWithNoInput(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        // Close stdin without writing anything
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(1, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No code provided', $result['error']);
    }

    /**
     * Test execution with timeout option
     */
    public function testExecuteWithTimeoutOption(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval --timeout 60',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'return "ok";');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertEquals(0, $exitCode);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('ok', $result['result']);
    }

    /**
     * Test execution strips PHP tags
     */
    public function testExecuteStripsPhpTags(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], '<?php return 42; ?>');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['result']);
    }

    /**
     * Test execution with object serialization
     */
    public function testExecuteSerializesObjects(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], '$obj = new \stdClass(); $obj->foo = "bar"; return $obj;');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('stdClass', $result['type']);
        $this->assertEquals('stdClass', $result['class']);
        // Object is serialized with __class and __properties
        $this->assertIsArray($result['result']);
        $this->assertEquals('stdClass', $result['result']['__class']);
        $this->assertEquals('bar', $result['result']['__properties']['foo']);
    }

    /**
     * Test execution returns null type correctly
     */
    public function testExecuteReturnsNullType(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            PHP_BINARY . ' bin/cake.php synapse tinker_eval',
            $descriptors,
            $pipes,
            ROOT,
        );

        $this->assertIsResource($process);

        fwrite($pipes[0], 'return null;');
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);

        $result = json_decode($stdout, true);
        $this->assertNotNull($result);
        $this->assertTrue($result['success']);
        $this->assertNull($result['result']);
        $this->assertEquals('null', $result['type']);
    }
}
