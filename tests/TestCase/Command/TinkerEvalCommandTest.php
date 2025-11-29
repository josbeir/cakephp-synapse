<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;
use JsonSerializable;
use ReflectionMethod;
use stdClass;
use Stringable;
use Synapse\Command\TinkerEvalCommand;

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

    private TinkerEvalCommand $command;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new TinkerEvalCommand();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->command);
        parent::tearDown();
    }

    // =========================================================================
    // Static Method Tests
    // =========================================================================

    /**
     * Test defaultName returns correct command name
     */
    public function testDefaultName(): void
    {
        $this->assertEquals('synapse tinker_eval', TinkerEvalCommand::defaultName());
    }

    /**
     * Test getDescription returns appropriate description
     */
    public function testGetDescription(): void
    {
        $description = TinkerEvalCommand::getDescription();

        $this->assertStringContainsString('Execute PHP code', $description);
        $this->assertStringContainsString('CakePHP application context', $description);
    }

    // =========================================================================
    // Option Parser Tests
    // =========================================================================

    /**
     * Test command help shows timeout option
     */
    public function testCommandHelp(): void
    {
        $this->exec('synapse tinker_eval --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Execute PHP code');
        $this->assertOutputContains('--timeout');
        $this->assertOutputContains('Maximum execution time');
    }

    /**
     * Test buildOptionParser configures timeout option correctly
     */
    public function testBuildOptionParserHasTimeoutOption(): void
    {
        $this->exec('synapse tinker_eval --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('-t');
        $this->assertOutputContains('--timeout');
    }

    // =========================================================================
    // serializeResult Tests (via reflection)
    // =========================================================================

    /**
     * Test serializeResult with null
     */
    public function testSerializeResultWithNull(): void
    {
        $result = $this->invokeSerializeResult(null);

        $this->assertNull($result);
    }

    /**
     * Test serializeResult with scalar values
     */
    public function testSerializeResultWithScalars(): void
    {
        $this->assertEquals(42, $this->invokeSerializeResult(42));
        $this->assertEquals(3.14, $this->invokeSerializeResult(3.14));
        $this->assertEquals('hello', $this->invokeSerializeResult('hello'));
        $this->assertTrue($this->invokeSerializeResult(true));
        $this->assertFalse($this->invokeSerializeResult(false));
    }

    /**
     * Test serializeResult with simple array
     */
    public function testSerializeResultWithArray(): void
    {
        $result = $this->invokeSerializeResult([1, 2, 3]);

        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test serializeResult with nested array
     */
    public function testSerializeResultWithNestedArray(): void
    {
        $input = ['a' => 1, 'b' => ['c' => 2, 'd' => [3, 4]]];
        $result = $this->invokeSerializeResult($input);

        $this->assertEquals($input, $result);
    }

    /**
     * Test serializeResult with array containing objects
     */
    public function testSerializeResultWithArrayContainingObjects(): void
    {
        $obj = new stdClass();
        $obj->name = 'test';

        $result = $this->invokeSerializeResult([$obj, 'string', 42]);

        $this->assertIsArray($result);
        $this->assertEquals('stdClass', $result[0]['__class']);
        $this->assertEquals('test', $result[0]['__properties']['name']);
        $this->assertEquals('string', $result[1]);
        $this->assertEquals(42, $result[2]);
    }

    /**
     * Test serializeResult with object having toArray method
     */
    public function testSerializeResultWithToArrayObject(): void
    {
        $obj = new class {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['key' => 'value', 'number' => 123];
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals(['key' => 'value', 'number' => 123], $result);
    }

    /**
     * Test serializeResult with JsonSerializable object
     */
    public function testSerializeResultWithJsonSerializableObject(): void
    {
        $obj = new class implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['serialized' => true, 'data' => 'test'];
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals(['serialized' => true, 'data' => 'test'], $result);
    }

    /**
     * Test serializeResult with Stringable object
     */
    public function testSerializeResultWithStringableObject(): void
    {
        $obj = new class implements Stringable {
            public function __toString(): string
            {
                return 'string representation';
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals('string representation', $result);
    }

    /**
     * Test serializeResult with object having __toString method (non-interface)
     */
    public function testSerializeResultWithToStringMethod(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'custom string';
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals('custom string', $result);
    }

    /**
     * Test serializeResult with plain stdClass object (fallback)
     */
    public function testSerializeResultWithStdClassFallback(): void
    {
        $obj = new stdClass();
        $obj->foo = 'bar';
        $obj->baz = 123;

        $result = $this->invokeSerializeResult($obj);

        $this->assertIsArray($result);
        $this->assertEquals('stdClass', $result['__class']);
        $this->assertEquals('bar', $result['__properties']['foo']);
        $this->assertEquals(123, $result['__properties']['baz']);
    }

    /**
     * Test serializeResult with resource
     */
    public function testSerializeResultWithResource(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->assertIsResource($resource);

        $result = $this->invokeSerializeResult($resource);

        fclose($resource);

        $this->assertIsArray($result);
        $this->assertEquals('resource', $result['__type']);
        $this->assertEquals('stream', $result['__resource_type']);
    }

    /**
     * Test serializeResult prioritizes toArray over jsonSerialize
     */
    public function testSerializeResultPrioritizesToArray(): void
    {
        $obj = new class implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return ['from' => 'toArray'];
            }

            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['from' => 'jsonSerialize'];
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals(['from' => 'toArray'], $result);
    }

    /**
     * Test serializeResult prioritizes jsonSerialize over __toString
     */
    public function testSerializeResultPrioritizesJsonSerialize(): void
    {
        $obj = new class implements JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['from' => 'jsonSerialize'];
            }

            public function __toString(): string
            {
                return 'from __toString';
            }
        };

        $result = $this->invokeSerializeResult($obj);

        $this->assertEquals(['from' => 'jsonSerialize'], $result);
    }

    // =========================================================================
    // Subprocess Integration Tests
    // =========================================================================

    /**
     * Test execution with simple expression via stdin simulation
     */
    public function testExecuteSimpleExpression(): void
    {
        $result = $this->executeInSubprocess('return 1 + 1;');

        $this->assertEquals(0, $result['exitCode']);
        $this->assertTrue($result['json']['success']);
        $this->assertEquals(2, $result['json']['result']);
        $this->assertEquals('int', $result['json']['type']);
    }

    /**
     * Test execution with array return value
     */
    public function testExecuteReturnsArray(): void
    {
        $result = $this->executeInSubprocess('return [1, 2, 3];');

        $this->assertEquals(0, $result['exitCode']);
        $this->assertTrue($result['json']['success']);
        $this->assertEquals([1, 2, 3], $result['json']['result']);
        $this->assertEquals('array', $result['json']['type']);
        $this->assertEquals(3, $result['json']['count']);
    }

    /**
     * Test execution with output capture
     */
    public function testExecuteWithOutput(): void
    {
        $result = $this->executeInSubprocess('echo "Hello World"; return "done";');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals('done', $result['json']['result']);
        $this->assertStringContainsString('Hello World', $result['json']['output']);
    }

    /**
     * Test execution with context access
     */
    public function testExecuteWithContextAccess(): void
    {
        $result = $this->executeInSubprocess('$table = $this->fetchTable("Users"); return $table::class;');

        $this->assertTrue($result['json']['success']);
        $this->assertStringContainsString('Table', $result['json']['result']);
    }

    /**
     * Test execution with exception
     */
    public function testExecuteWithException(): void
    {
        $result = $this->executeInSubprocess('throw new \Exception("Test error");');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertEquals('Test error', $result['json']['error']);
        $this->assertEquals('Exception', $result['json']['type']);
        $this->assertArrayHasKey('trace', $result['json']);
        $this->assertArrayHasKey('file', $result['json']);
        $this->assertArrayHasKey('line', $result['json']);
    }

    /**
     * Test execution with syntax error
     */
    public function testExecuteWithSyntaxError(): void
    {
        $result = $this->executeInSubprocess('invalid php code here;');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertArrayHasKey('error', $result['json']);
    }

    /**
     * Test execution with no input returns error
     */
    public function testExecuteWithNoInput(): void
    {
        $result = $this->executeInSubprocess('');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertStringContainsString('No code provided', $result['json']['error']);
        $this->assertEquals('InvalidArgumentException', $result['json']['type']);
    }

    /**
     * Test execution with whitespace-only input returns error
     */
    public function testExecuteWithWhitespaceOnlyInput(): void
    {
        $result = $this->executeInSubprocess("   \n\t  ");

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertStringContainsString('No code provided', $result['json']['error']);
    }

    /**
     * Test execution with timeout option
     */
    public function testExecuteWithTimeoutOption(): void
    {
        $result = $this->executeInSubprocess('return "ok";', ['--timeout', '60']);

        $this->assertEquals(0, $result['exitCode']);
        $this->assertTrue($result['json']['success']);
        $this->assertEquals('ok', $result['json']['result']);
    }

    /**
     * Test execution with short timeout option
     */
    public function testExecuteWithShortTimeoutOption(): void
    {
        $result = $this->executeInSubprocess('return "ok";', ['-t', '45']);

        $this->assertEquals(0, $result['exitCode']);
        $this->assertTrue($result['json']['success']);
    }

    /**
     * Test execution strips PHP tags
     */
    public function testExecuteStripsPhpTags(): void
    {
        $result = $this->executeInSubprocess('<?php return 42; ?>');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(42, $result['json']['result']);
    }

    /**
     * Test execution strips short PHP tags
     */
    public function testExecuteStripsShortPhpTags(): void
    {
        $result = $this->executeInSubprocess('<? return 42; ?>');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(42, $result['json']['result']);
    }

    /**
     * Test execution with object serialization
     */
    public function testExecuteSerializesObjects(): void
    {
        $result = $this->executeInSubprocess('$obj = new \stdClass(); $obj->foo = "bar"; return $obj;');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals('stdClass', $result['json']['type']);
        $this->assertEquals('stdClass', $result['json']['class']);
        $this->assertIsArray($result['json']['result']);
        $this->assertEquals('stdClass', $result['json']['result']['__class']);
        $this->assertEquals('bar', $result['json']['result']['__properties']['foo']);
    }

    /**
     * Test execution returns null type correctly
     */
    public function testExecuteReturnsNullType(): void
    {
        $result = $this->executeInSubprocess('return null;');

        $this->assertTrue($result['json']['success']);
        $this->assertNull($result['json']['result']);
        $this->assertEquals('null', $result['json']['type']);
    }

    /**
     * Test execution with boolean return values
     */
    public function testExecuteReturnsBooleans(): void
    {
        $resultTrue = $this->executeInSubprocess('return true;');
        $this->assertTrue($resultTrue['json']['success']);
        $this->assertTrue($resultTrue['json']['result']);
        $this->assertEquals('bool', $resultTrue['json']['type']);

        $resultFalse = $this->executeInSubprocess('return false;');
        $this->assertTrue($resultFalse['json']['success']);
        $this->assertFalse($resultFalse['json']['result']);
        $this->assertEquals('bool', $resultFalse['json']['type']);
    }

    /**
     * Test execution with float return value
     */
    public function testExecuteReturnsFloat(): void
    {
        $result = $this->executeInSubprocess('return 3.14159;');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(3.14159, $result['json']['result']);
        $this->assertEquals('float', $result['json']['type']);
    }

    /**
     * Test execution with string return value
     */
    public function testExecuteReturnsString(): void
    {
        $result = $this->executeInSubprocess('return "hello world";');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals('hello world', $result['json']['result']);
        $this->assertEquals('string', $result['json']['type']);
    }

    /**
     * Test execution with no return statement
     */
    public function testExecuteWithNoReturn(): void
    {
        $result = $this->executeInSubprocess('$x = 1 + 1;');

        $this->assertTrue($result['json']['success']);
        $this->assertNull($result['json']['result']);
        $this->assertEquals('null', $result['json']['type']);
    }

    /**
     * Test execution with multiple statements
     */
    public function testExecuteWithMultipleStatements(): void
    {
        $code = '$a = 10; $b = 20; $c = $a + $b; return $c * 2;';
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(60, $result['json']['result']);
    }

    /**
     * Test execution with multiline code
     */
    public function testExecuteWithMultilineCode(): void
    {
        $code = <<<'PHP'
$numbers = [1, 2, 3, 4, 5];
$sum = array_sum($numbers);
$avg = $sum / count($numbers);
return ['sum' => $sum, 'avg' => $avg];
PHP;
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(['sum' => 15, 'avg' => 3], $result['json']['result']);
    }

    /**
     * Test execution captures multiple echo statements
     */
    public function testExecuteCapturesMultipleOutputs(): void
    {
        $result = $this->executeInSubprocess('echo "Line 1\n"; echo "Line 2\n"; print "Line 3"; return "done";');

        $this->assertTrue($result['json']['success']);
        $this->assertStringContainsString('Line 1', $result['json']['output']);
        $this->assertStringContainsString('Line 2', $result['json']['output']);
        $this->assertStringContainsString('Line 3', $result['json']['output']);
    }

    /**
     * Test execution with object having toArray method
     */
    public function testExecuteSerializesToArrayObject(): void
    {
        $code = <<<'PHP'
return new class {
    public function toArray(): array {
        return ['converted' => 'toArray'];
    }
};
PHP;
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(['converted' => 'toArray'], $result['json']['result']);
    }

    /**
     * Test execution with JsonSerializable object
     */
    public function testExecuteSerializesJsonSerializableObject(): void
    {
        $code = <<<'PHP'
return new class implements \JsonSerializable {
    public function jsonSerialize(): array {
        return ['json' => 'serializable'];
    }
};
PHP;
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(['json' => 'serializable'], $result['json']['result']);
    }

    /**
     * Test execution with Stringable object
     */
    public function testExecuteSerializesStringableObject(): void
    {
        $code = <<<'PHP'
return new class implements \Stringable {
    public function __toString(): string {
        return 'stringable object';
    }
};
PHP;
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertEquals('stringable object', $result['json']['result']);
    }

    /**
     * Test execution with nested objects in array
     */
    public function testExecuteSerializesNestedObjectsInArray(): void
    {
        $code = <<<'PHP'
$obj1 = new \stdClass();
$obj1->name = 'first';
$obj2 = new \stdClass();
$obj2->name = 'second';
return ['objects' => [$obj1, $obj2], 'count' => 2];
PHP;
        $result = $this->executeInSubprocess($code);

        $this->assertTrue($result['json']['success']);
        $this->assertIsArray($result['json']['result']);
        $this->assertEquals(2, $result['json']['result']['count']);
        $this->assertEquals('first', $result['json']['result']['objects'][0]['__properties']['name']);
        $this->assertEquals('second', $result['json']['result']['objects'][1]['__properties']['name']);
    }

    /**
     * Test execution with RuntimeException
     */
    public function testExecuteWithRuntimeException(): void
    {
        $result = $this->executeInSubprocess('throw new \RuntimeException("Runtime error");');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertEquals('Runtime error', $result['json']['error']);
        $this->assertEquals('RuntimeException', $result['json']['type']);
    }

    /**
     * Test execution with custom exception
     */
    public function testExecuteWithInvalidArgumentException(): void
    {
        $result = $this->executeInSubprocess('throw new \InvalidArgumentException("Invalid arg");');

        $this->assertEquals(1, $result['exitCode']);
        $this->assertFalse($result['json']['success']);
        $this->assertEquals('Invalid arg', $result['json']['error']);
        $this->assertEquals('InvalidArgumentException', $result['json']['type']);
    }

    /**
     * Test execution error includes file information
     */
    public function testExecuteErrorIncludesFileInfo(): void
    {
        $result = $this->executeInSubprocess('throw new \Exception("Test");');

        $this->assertFalse($result['json']['success']);
        $this->assertArrayHasKey('file', $result['json']);
        $this->assertArrayHasKey('line', $result['json']);
        $this->assertIsString($result['json']['file']);
        $this->assertIsInt($result['json']['line']);
    }

    /**
     * Test execution error trace is string
     */
    public function testExecuteErrorTraceIsString(): void
    {
        $result = $this->executeInSubprocess('throw new \Exception("Test");');

        $this->assertFalse($result['json']['success']);
        $this->assertArrayHasKey('trace', $result['json']);
        $this->assertIsString($result['json']['trace']);
        $this->assertNotEmpty($result['json']['trace']);
    }

    /**
     * Test execution with empty array
     */
    public function testExecuteReturnsEmptyArray(): void
    {
        $result = $this->executeInSubprocess('return [];');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals([], $result['json']['result']);
        $this->assertEquals('array', $result['json']['type']);
        $this->assertEquals(0, $result['json']['count']);
    }

    /**
     * Test execution with associative array
     */
    public function testExecuteReturnsAssociativeArray(): void
    {
        $result = $this->executeInSubprocess('return ["name" => "test", "value" => 42];');

        $this->assertTrue($result['json']['success']);
        $this->assertEquals(['name' => 'test', 'value' => 42], $result['json']['result']);
        $this->assertEquals(2, $result['json']['count']);
    }

    /**
     * Test output is null when nothing is echoed
     */
    public function testExecuteOutputIsNullWhenEmpty(): void
    {
        $result = $this->executeInSubprocess('return 42;');

        $this->assertTrue($result['json']['success']);
        $this->assertNull($result['json']['output']);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Invoke the private serializeResult method via reflection
     *
     * @param mixed $value Value to serialize
     * @return mixed Serialized result
     */
    private function invokeSerializeResult(mixed $value): mixed
    {
        $method = new ReflectionMethod(TinkerEvalCommand::class, 'serializeResult');

        return $method->invoke($this->command, $value);
    }

    /**
     * Execute code in subprocess and return parsed result
     *
     * @param string $code PHP code to execute
     * @param array<string> $options Additional command options
     * @return array{exitCode: int, stdout: string, stderr: string, json: array<string, mixed>}
     */
    private function executeInSubprocess(string $code, array $options = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = PHP_BINARY . ' bin/cake.php synapse tinker_eval';
        if ($options !== []) {
            $command .= ' ' . implode(' ', $options);
        }

        $process = proc_open($command, $descriptors, $pipes, ROOT);

        $this->assertIsResource($process);

        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $json = null;
        if ($stdout !== false && $stdout !== '') {
            $json = json_decode($stdout, true);
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
            'json' => $json ?? [],
        ];
    }
}
