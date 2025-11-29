<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\Core\Configure;
use Mcp\Capability\Attribute\McpTool;
use Throwable;

/**
 * Tinker Tools
 *
 * Execute PHP code in the CakePHP application context for debugging and testing.
 * Code is executed in a subprocess to ensure the latest code from disk is loaded.
 */
class TinkerTools
{
    /**
     * Path to the PHP executable (cached)
     */
    private ?string $phpBinary = null;

    /**
     * Path to the CakePHP bin directory (cached)
     */
    private ?string $binPath = null;

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
    )]
    public function execute(string $code, int $timeout = 30): array
    {
        // Validate timeout bounds
        $timeout = min(max(1, $timeout), 180);

        $phpBinary = $this->getPhpBinary();
        $binPath = $this->getBinPath();

        if ($phpBinary === null) {
            return [
                'success' => false,
                'error' => 'Could not find PHP binary. Configure Synapse.tinker.php_binary or ensure php is in PATH.',
                'type' => 'RuntimeException',
            ];
        }

        // The command uses bin/cake.php directly (not the shell wrapper)
        $command = sprintf(
            '%s bin/cake.php synapse tinker_eval --timeout %d',
            escapeshellarg($phpBinary),
            $timeout,
        );

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        // Set working directory to application root (parent of bin directory)
        $cwd = dirname($binPath);

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'error' => 'Failed to start subprocess',
                'type' => 'RuntimeException',
            ];
        }

        try {
            // Write code to stdin
            fwrite($pipes[0], $code);
            fclose($pipes[0]);

            // Set stream to non-blocking for timeout handling
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = '';
            $stderr = '';
            $startTime = time();

            // Read output with timeout handling
            while (true) {
                $status = proc_get_status($process);

                // Check if process has exited
                if (!$status['running']) {
                    // Read any remaining output
                    $stdout .= stream_get_contents($pipes[1]);
                    $stderr .= stream_get_contents($pipes[2]);
                    break;
                }

                // Check timeout
                if (time() - $startTime > $timeout) {
                    proc_terminate($process, 9);

                    return [
                        'success' => false,
                        'error' => sprintf('Execution timed out after %d seconds', $timeout),
                        'type' => 'RuntimeException',
                    ];
                }

                // Read available output
                $stdout .= fread($pipes[1], 8192) ?: '';
                $stderr .= fread($pipes[2], 8192) ?: '';

                // Small sleep to prevent CPU spinning
                usleep(10000); // 10ms
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            // Parse JSON response from stdout
            $stdout = trim($stdout);

            if ($stdout === '') {
                return [
                    'success' => false,
                    'error' => $stderr ?: 'No output from subprocess',
                    'type' => 'RuntimeException',
                    'exit_code' => $exitCode,
                ];
            }

            $result = json_decode($stdout, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse subprocess output: ' . json_last_error_msg(),
                    'type' => 'RuntimeException',
                    'raw_output' => $stdout,
                    'stderr' => $stderr ?: null,
                ];
            }

            return $result;
        } catch (Throwable $throwable) {
            // Ensure process is cleaned up
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }

            return [
                'success' => false,
                'error' => $throwable->getMessage(),
                'type' => $throwable::class,
            ];
        }
    }

    /**
     * Get the PHP binary path.
     *
     * Resolution order:
     * 1. Explicitly set via setPhpBinary()
     * 2. Configuration: Synapse.tinker.php_binary
     * 3. `which php` command
     * 4. PHP_BINARY constant
     *
     * @return string|null Path to PHP binary or null if not found
     */
    public function getPhpBinary(): ?string
    {
        if ($this->phpBinary !== null) {
            return $this->phpBinary;
        }

        // Check configuration
        $configured = Configure::read('Synapse.tinker.php_binary');
        if ($configured !== null && is_string($configured) && is_executable($configured)) {
            return $configured;
        }

        // Try `which php` command (most reliable for finding the active PHP)
        $whichResult = shell_exec('which php 2>/dev/null');
        if (is_string($whichResult) && trim($whichResult) !== '') {
            $which = trim($whichResult);
            if (is_executable($which)) {
                return $which;
            }
        }

        // Fallback to PHP_BINARY constant (always defined and non-empty in PHP 8.2+)
        if (is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }

        return null;
    }

    /**
     * Get the CakePHP bin directory path.
     *
     * Resolution order:
     * 1. Explicitly set via setBinPath()
     * 2. Configuration: Synapse.tinker.bin_path
     * 3. ROOT constant + /bin
     * 4. Current working directory + /bin
     *
     * @return string Path to bin directory
     */
    public function getBinPath(): string
    {
        if ($this->binPath !== null) {
            return $this->binPath;
        }

        // Check configuration
        $configured = Configure::read('Synapse.tinker.bin_path');
        if ($configured !== null && is_string($configured)) {
            return $configured;
        }

        // Check if ROOT constant is defined (CakePHP app)
        if (defined('ROOT')) {
            return ROOT . '/bin';
        }

        // Fallback to current working directory
        return getcwd() . '/bin';
    }

    /**
     * Set the PHP binary path.
     *
     * @param string|null $path Path to PHP binary
     */
    public function setPhpBinary(?string $path): static
    {
        $this->phpBinary = $path;

        return $this;
    }

    /**
     * Set the bin directory path.
     *
     * @param string|null $path Path to bin directory
     */
    public function setBinPath(?string $path): static
    {
        $this->binPath = $path;

        return $this;
    }
}
