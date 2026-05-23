<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Finder\Finder;

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
        description: 'List all available log files in the application logs directory. ' .
            'Returns an empty array when no .log files exist or when the application uses ' .
            'non-filesystem logging (database, syslog, etc.).',
    )]
    public function listLogs(): array
    {
        $logsDir = $this->logsDir();

        if (!is_dir($logsDir)) {
            return [];
        }

        $finder = (new Finder())
            ->files()
            ->name('*.log')
            ->depth(0)
            ->in($logsDir);

        $result = [];
        foreach ($finder as $file) {
            $result[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => date('c', $file->getMTime()),
                'path' => $file->getRealPath(),
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
        $lines = max(1, min(5000, $lines));
        $path = $this->resolveLogPath($file);

        $tail = $level !== null
            ? $this->readTailFiltered($path, $lines, $level, $file)
            : $this->readTail($path, $lines, $file);

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

    /**
     * Read the last N lines from a file using a reverse-seek strategy.
     *
     * Reads backwards in 8 KB chunks so only the tail portion of the file
     * is loaded into memory, regardless of total file size.
     *
     * @param string $path Absolute file path
     * @param int $lines Number of lines to return
     * @param string $file Original user-supplied name (for error messages)
     * @return array<int, string> Lines from the tail of the file
     * @throws \Mcp\Exception\ToolCallException When the file cannot be opened
     */
    private function readTail(string $path, int $lines, string $file): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new ToolCallException(sprintf("Could not read log file '%s'.", $file));
        }

        try {
            fseek($handle, 0, SEEK_END);
            $fileSize = ftell($handle);
            if (!is_int($fileSize) || $fileSize === 0) {
                return [];
            }

            $buffer = '';
            $pos = $fileSize;
            $chunkSize = 8192;
            $linesFound = 0;

            while ($pos > 0 && $linesFound <= $lines) {
                $readSize = min($chunkSize, $pos);
                $pos -= $readSize;
                fseek($handle, $pos);
                $chunk = fread($handle, $readSize);
                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk . $buffer;
                $linesFound += substr_count($chunk, "\n");
            }
        } finally {
            fclose($handle);
        }

        $result = explode("\n", $buffer);
        if (end($result) === '') {
            array_pop($result);
        }

        return array_slice($result, -$lines);
    }

    /**
     * Read lines from a file that match a level string, returning the last N matches.
     *
     * Processes the file line by line using a fixed-size rolling buffer so that
     * memory use is bounded to $lines matching entries regardless of file size.
     *
     * @param string $path Absolute file path
     * @param int $lines Maximum number of matching lines to return
     * @param string $level Level string to filter on (case-insensitive)
     * @param string $file Original user-supplied name (for error messages)
     * @return array<int, string> Matching lines from the tail of the file
     * @throws \Mcp\Exception\ToolCallException When the file cannot be opened
     */
    private function readTailFiltered(string $path, int $lines, string $level, string $file): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new ToolCallException(sprintf("Could not read log file '%s'.", $file));
        }

        $matching = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\n\r");
                if (stripos($line, $level) !== false) {
                    $matching[] = $line;
                    if (count($matching) > $lines) {
                        array_shift($matching);
                    }
                }
            }
        } finally {
            fclose($handle);
        }

        return $matching;
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
