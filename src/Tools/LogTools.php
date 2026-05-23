<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Log Tools
 *
 * MCP tools for reading and inspecting CakePHP application log files.
 * All file access is validated to stay within the configured LOGS directory.
 */
class LogTools
{
    /**
     * List all available log files in the application LOGS directory.
     *
     * @return array<int, array{name: string, size: int, modified: string, path: string}>
     */
    #[McpTool(
        name: 'log_list',
        description: 'List all available log files in the application logs directory.',
    )]
    public function listLogs(): array
    {
        $logsDir = $this->logsDir();

        if (!is_dir($logsDir)) {
            return [];
        }

        $files = glob($logsDir . '*.log');
        if ($files === false) {
            return [];
        }

        $result = [];
        foreach ($files as $path) {
            $stat = stat($path);
            $result[] = [
                'name' => basename($path),
                'size' => $stat !== false ? (int)$stat['size'] : 0,
                'modified' => $stat !== false ? date('c', (int)$stat['mtime']) : '',
                'path' => $path,
            ];
        }

        usort($result, fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Read lines from a log file, optionally filtered by log level.
     *
     * @param string $file Log file name (relative to LOGS directory, e.g. 'app.log')
     * @param int $lines Maximum number of lines to return from the end of the file (tail)
     * @param string|null $level Filter lines containing this string (case-insensitive). Pass null to return all.
     * @return array{file: string, lines: int, content: string}
     * @throws \Mcp\Exception\ToolCallException When the file is not found or path is invalid
     */
    #[McpTool(
        name: 'log_read',
        description: 'Read the last N lines from a log file, optionally filtered by log level. ' .
            'Use log_list to discover available files.',
    )]
    public function readLog(string $file, int $lines = 100, ?string $level = null): array
    {
        $path = $this->resolveLogPath($file);

        $rawLines = file($path, FILE_IGNORE_NEW_LINES);
        if ($rawLines === false) {
            throw new ToolCallException(sprintf("Could not read log file '%s'.", $file));
        }

        // Filter by level first, then take the tail
        if ($level !== null) {
            $rawLines = array_values(
                array_filter($rawLines, fn(string $line): bool => stripos($line, $level) !== false),
            );
        }

        $lines = max(1, $lines);
        $tail = array_slice($rawLines, -$lines);

        $content = implode("\n", $tail);
        if ($content !== '') {
            $content .= "\n";
        }

        return [
            'file' => basename($path),
            'lines' => count($tail),
            'content' => $content,
        ];
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Resolve and validate a file name to an absolute path inside LOGS.
     *
     * @throws \Mcp\Exception\ToolCallException When the resolved path escapes the LOGS directory or does not exist.
     */
    private function resolveLogPath(string $file): string
    {
        $logsDir = $this->logsDir();

        // Build candidate path — strip any leading slashes/dots so the
        // basename is always relative to logsDir.
        $candidate = $logsDir . basename($file);

        // realpath requires the file to exist; use it to detect traversal.
        $real = realpath($candidate);
        $realDir = realpath($logsDir);

        if ($real === false || $realDir === false) {
            throw new ToolCallException(sprintf("Log file '%s' not found.", $file));
        }

        if (!str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            throw new ToolCallException(sprintf("Log file '%s' is outside the logs directory.", $file));
        }

        return $real;
    }

    /**
     * Return the LOGS directory path with a trailing separator.
     */
    private function logsDir(): string
    {
        return rtrim(LOGS, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
