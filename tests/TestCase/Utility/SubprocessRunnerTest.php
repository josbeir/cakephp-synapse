<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Utility;

use Cake\TestSuite\TestCase;
use Synapse\Utility\SubprocessRunner;

/**
 * SubprocessRunner Test Case
 */
class SubprocessRunnerTest extends TestCase
{
    private SubprocessRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = new SubprocessRunner();
    }

    protected function tearDown(): void
    {
        unset($this->runner);
        parent::tearDown();
    }

    // =========================================================================
    // Binary / Path Detection
    // =========================================================================

    public function testGetPhpBinaryReturnsExecutable(): void
    {
        $binary = $this->runner->getPhpBinary();

        $this->assertNotNull($binary);
        $this->assertTrue(is_executable($binary), sprintf("PHP binary '%s' must be executable", $binary));
    }

    public function testSetPhpBinaryOverridesAutoDetection(): void
    {
        $this->runner->setPhpBinary('/custom/php');
        $this->assertSame('/custom/php', $this->runner->getPhpBinary());
    }

    public function testSetPhpBinaryNullResetsToAutoDetection(): void
    {
        $this->runner->setPhpBinary('/custom/php');
        $this->runner->setPhpBinary(null);

        $binary = $this->runner->getPhpBinary();
        $this->assertNotNull($binary);
        $this->assertNotSame('/custom/php', $binary);
    }

    public function testSetPhpBinaryReturnsStatic(): void
    {
        $result = $this->runner->setPhpBinary(null);
        $this->assertSame($this->runner, $result);
    }

    public function testGetBinPathEndsWithBin(): void
    {
        $binPath = $this->runner->getBinPath();
        $this->assertStringEndsWith('bin', $binPath);
    }

    public function testSetBinPathOverrides(): void
    {
        $this->runner->setBinPath('/custom/bin');
        $this->assertSame('/custom/bin', $this->runner->getBinPath());
    }

    public function testSetBinPathReturnsStatic(): void
    {
        $result = $this->runner->setBinPath('/custom/bin');
        $this->assertSame($this->runner, $result);
    }

    // =========================================================================
    // run() — result structure
    // =========================================================================

    public function testRunReturnsMandatoryKeys(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "exit(0);"');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertArrayHasKey('stderr', $result);
        $this->assertArrayHasKey('exit_code', $result);
    }

    public function testRunCapturesStdout(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "echo \'hello world\';"');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('hello world', $result['output']);
        $this->assertSame(0, $result['exit_code']);
    }

    public function testRunCapturesStderr(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "fwrite(STDERR, \'err text\');"');

        $this->assertStringContainsString('err text', $result['stderr']);
    }

    public function testRunNonZeroExitCodeReturnsFalseSuccess(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "exit(42);"');

        $this->assertFalse($result['success']);
        $this->assertSame(42, $result['exit_code']);
    }

    public function testRunPipesStdinToProcess(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "echo fgets(STDIN);"', 'piped input');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('piped input', $result['output']);
    }

    public function testRunWithNullStdinDoesNotBlock(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "echo \'ok\';"');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('ok', $result['output']);
    }

    public function testRunWithCustomCwd(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $cwd = sys_get_temp_dir();
        $result = $this->runner->run($binary . ' -r "echo getcwd();"', null, 10, $cwd);

        $this->assertTrue($result['success']);
        // Output should contain the temp dir (realpath to handle symlinks)
        $this->assertStringContainsString(basename(realpath($cwd) ?: $cwd), $result['output']);
    }

    public function testRunTimeoutKillsProcess(): void
    {
        $binary = escapeshellarg(PHP_BINARY);
        $result = $this->runner->run($binary . ' -r "sleep(10);"', null, 1);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['timed_out'] ?? false);
    }
}
