<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Synapse\Utility\SubprocessRunner;

/**
 * Tinker Tools
 *
 * Execute PHP code in the CakePHP application context for debugging and testing.
 * Code is executed in a subprocess to ensure the latest code from disk is loaded.
 */
class TinkerTools
{
    /**
     * @param \Synapse\Utility\SubprocessRunner $runner Subprocess execution utility.
     */
    public function __construct(private SubprocessRunner $runner = new SubprocessRunner())
    {
    }

    /**
     * Execute PHP code in the application context.
     *
     * Code executes in a subprocess that loads the latest code from disk.
     * Use $context->fetchTable(), $context->log(), etc. for CakePHP functionality.
     *
     * @param string $code PHP code to execute (without opening <?php tags)
     * @param int $timeout Maximum execution time in seconds (default: 30, max: 180)
     * @return array<string, mixed> Execution result with output, return value, and type info
     */
    #[McpTool(
        name: 'tinker',
        description: 'Execute PHP code in the CakePHP application context. ' .
            'Use for debugging, testing code snippets, and exploring the application. ' .
            'DO NOT create/modify data without explicit user approval. ' .
            'Prefer feature tests and existing commands over custom code. ' .
            'Use $this->fetchTable() and $this->log() for ORM and logging access.',
        annotations: new ToolAnnotations(destructiveHint: true, idempotentHint: false),
    )]
    public function execute(string $code, int $timeout = 30): array
    {
        $timeout = min(max(1, $timeout), 180);

        $phpBinary = $this->runner->getPhpBinary();
        $binPath = $this->runner->getBinPath();

        if ($phpBinary === null) {
            return [
                'success' => false,
                'error' => 'Could not find PHP binary. Configure Synapse.tinker.php_binary or ensure php is in PATH.',
                'type' => 'RuntimeException',
            ];
        }

        $command = sprintf(
            '%s bin/cake.php synapse tinker_eval --timeout %d',
            escapeshellarg($phpBinary),
            $timeout,
        );

        $cwd = dirname($binPath);
        $raw = $this->runner->run($command, $code, $timeout, $cwd);

        if ($raw['timed_out'] ?? false) {
            return [
                'success' => false,
                'error' => sprintf('Execution timed out after %d seconds', $timeout),
                'type' => 'RuntimeException',
            ];
        }

        // proc_open failure: no output, exit code -1, no timeout
        if (!$raw['success'] && $raw['output'] === '' && $raw['exit_code'] === -1) {
            return [
                'success' => false,
                'error' => 'Failed to start subprocess',
                'type' => 'RuntimeException',
            ];
        }

        $stdout = trim($raw['output']);

        if ($stdout === '') {
            return [
                'success' => false,
                'error' => $raw['stderr'] ?: 'No output from subprocess',
                'type' => 'RuntimeException',
                'exit_code' => $raw['exit_code'],
            ];
        }

        $result = json_decode($stdout, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Failed to parse subprocess output: ' . json_last_error_msg(),
                'type' => 'RuntimeException',
                'raw_output' => $stdout,
                'stderr' => $raw['stderr'] ?: null,
            ];
        }

        return $result;
    }

    /**
     * Get the PHP binary path (delegates to SubprocessRunner).
     *
     * @return string|null Path to PHP binary or null if not found
     */
    public function getPhpBinary(): ?string
    {
        return $this->runner->getPhpBinary();
    }

    /**
     * Get the CakePHP bin directory path (delegates to SubprocessRunner).
     *
     * @return string Path to bin directory
     */
    public function getBinPath(): string
    {
        return $this->runner->getBinPath();
    }

    /**
     * Set the PHP binary path (delegates to SubprocessRunner).
     *
     * @param string|null $path Path to PHP binary
     */
    public function setPhpBinary(?string $path): static
    {
        $this->runner->setPhpBinary($path);

        return $this;
    }

    /**
     * Set the bin directory path (delegates to SubprocessRunner).
     *
     * @param string|null $path Path to bin directory
     */
    public function setBinPath(?string $path): static
    {
        $this->runner->setBinPath($path);

        return $this;
    }
}
