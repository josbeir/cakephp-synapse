<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\Tools\LogTools;

/**
 * LogTools Test Case
 *
 * Tests for log listing and reading MCP tools.
 * Uses the LOGS constant (tmp/logs/) defined in bootstrap.php.
 */
class LogToolsTest extends TestCase
{
    private LogTools $logTools;

    /** @var array<string> paths to clean up after each test */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!is_dir(LOGS)) {
            mkdir(LOGS, 0755, true);
        }

        $this->logTools = new LogTools();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->createdFiles = [];

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeLog(string $filename, string $content): string
    {
        $path = LOGS . $filename;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    // -------------------------------------------------------------------------
    // log_list
    // -------------------------------------------------------------------------

    /**
     * Test log_list returns an array.
     */
    public function testLogListReturnsArray(): void
    {
        $result = $this->logTools->listLogs();

        $this->assertGreaterThanOrEqual(0, count($result));
    }

    /**
     * Test log_list includes existing log files.
     */
    public function testLogListIncludesLogFiles(): void
    {
        $this->writeLog('app.log', "line1\nline2\n");
        $this->writeLog('error.log', "err\n");

        $result = $this->logTools->listLogs();

        $names = array_column($result, 'name');
        $this->assertContains('app.log', $names);
        $this->assertContains('error.log', $names);
    }

    /**
     * Test log_list entry has required keys.
     */
    public function testLogListEntryHasRequiredKeys(): void
    {
        $this->writeLog('test.log', "content\n");

        $result = $this->logTools->listLogs();

        $entry = current(array_filter($result, fn(array $e): bool => $e['name'] === 'test.log'));
        $this->assertNotFalse($entry);
        $this->assertArrayHasKey('name', $entry);
        $this->assertArrayHasKey('size', $entry);
        $this->assertArrayHasKey('modified', $entry);
        $this->assertArrayHasKey('path', $entry);
    }

    /**
     * Test log_list does not include non-.log files.
     */
    public function testLogListOnlyIncludesLogFiles(): void
    {
        $notLogPath = LOGS . 'readme.txt';
        file_put_contents($notLogPath, 'not a log');
        $this->createdFiles[] = $notLogPath;

        $result = $this->logTools->listLogs();

        $names = array_column($result, 'name');
        $this->assertNotContains('readme.txt', $names);
    }

    /**
     * Test log_list size is accurate.
     */
    public function testLogListSizeIsAccurate(): void
    {
        $content = str_repeat('x', 42);
        $this->writeLog('sized.log', $content);

        $result = $this->logTools->listLogs();

        $entry = current(array_filter($result, fn(array $e): bool => $e['name'] === 'sized.log'));
        $this->assertNotFalse($entry);
        $this->assertSame(42, $entry['size']);
    }

    // -------------------------------------------------------------------------
    // log_read
    // -------------------------------------------------------------------------

    /**
     * Test log_read returns content of a log file.
     */
    public function testLogReadReturnsContent(): void
    {
        $this->writeLog('app.log', "line1\nline2\nline3\n");

        $result = $this->logTools->readLog('app.log');

        $this->assertArrayHasKey('file', $result);
        $this->assertArrayHasKey('lines', $result);
        $this->assertArrayHasKey('content', $result);
        $this->assertStringContainsString('line1', $result['content']);
    }

    /**
     * Test log_read respects the lines limit (tail behaviour).
     */
    public function testLogReadRespectsLinesLimit(): void
    {
        $lines = implode("\n", range(1, 100)) . "\n";
        $this->writeLog('big.log', $lines);

        $result = $this->logTools->readLog('big.log', 10);

        $returned = count(array_filter(explode("\n", trim($result['content'])), fn($l): bool => $l !== ''));
        $this->assertLessThanOrEqual(10, $returned);
        $this->assertStringContainsString('100', $result['content']); // tail = last lines
    }

    /**
     * Test log_read filters by log level.
     */
    public function testLogReadFiltersByLevel(): void
    {
        $content = implode("\n", [
            '2026-01-01 00:00:00 Error: something broke',
            '2026-01-01 00:00:01 Warning: watch out',
            '2026-01-01 00:00:02 Error: another error',
        ]) . "\n";
        $this->writeLog('mixed.log', $content);

        $result = $this->logTools->readLog('mixed.log', 100, 'Error');

        $this->assertStringContainsString('Error', $result['content']);
        $this->assertStringNotContainsString('Warning', $result['content']);
    }

    /**
     * Test log_read with null level returns all lines.
     */
    public function testLogReadWithNullLevelReturnsAll(): void
    {
        $content = "Error: foo\nWarning: bar\nDebug: baz\n";
        $this->writeLog('all.log', $content);

        $result = $this->logTools->readLog('all.log', 100);

        $this->assertStringContainsString('Error', $result['content']);
        $this->assertStringContainsString('Warning', $result['content']);
        $this->assertStringContainsString('Debug', $result['content']);
    }

    /**
     * Test log_read throws for unknown file.
     */
    public function testLogReadThrowsForUnknownFile(): void
    {
        $this->expectException(ToolCallException::class);

        $this->logTools->readLog('nonexistent_xyz.log');
    }

    /**
     * Test log_read rejects path traversal attempts.
     */
    public function testLogReadRejectsPathTraversal(): void
    {
        $this->expectException(ToolCallException::class);

        $this->logTools->readLog('../composer.json');
    }

    /**
     * Test log_read rejects absolute paths outside LOGS.
     */
    public function testLogReadRejectsAbsolutePath(): void
    {
        $this->expectException(ToolCallException::class);

        $this->logTools->readLog('/etc/passwd');
    }

    /**
     * Test log_read lines field reflects actual line count returned.
     */
    public function testLogReadLinesFieldIsAccurate(): void
    {
        $this->writeLog('count.log', "a\nb\nc\n");

        $result = $this->logTools->readLog('count.log', 100);

        $this->assertSame(3, $result['lines']);
    }
}
