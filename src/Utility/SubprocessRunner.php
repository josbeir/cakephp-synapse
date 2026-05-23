<?php
declare(strict_types=1);

namespace Synapse\Utility;

use Cake\Core\Configure;

/**
 * SubprocessRunner
 *
 * Encapsulates subprocess execution via proc_open, handling stdin piping,
 * stdout/stderr capture, timeout enforcement, and PHP/bin path detection.
 *
 * Used by TinkerTools and CommandTools to avoid duplicating subprocess logic.
 */
class SubprocessRunner
{
    /**
     * Path to the PHP executable (cached after first resolution).
     */
    private ?string $phpBinary = null;

    /**
     * Path to the CakePHP bin directory (cached after first resolution).
     */
    private ?string $binPath = null;

    /**
     * Run an arbitrary shell command, optionally piping data to stdin.
     *
     * @param string $command The full shell command to execute.
     * @param string|null $stdin Data to pipe to stdin, or null to close stdin immediately.
     * @param int $timeout Maximum execution time in seconds.
     * @param string|null $cwd Working directory for the process, or null for current dir.
     * @return array{success: bool, output: string, stderr: string, exit_code: int, timed_out?: true}
     */
    public function run(string $command, ?string $stdin = null, int $timeout = 30, ?string $cwd = null): array
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);

        if (!is_resource($process)) {
            return [
                'success' => false,
                'output' => '',
                'stderr' => 'Failed to start process',
                'exit_code' => -1,
            ];
        }

        // Capture outside try so finally can reference them.
        $exitCode = -1;
        $timedOut = false;
        $stdout = '';
        $stderr = '';

        try {
            // Write stdin then close so the process sees EOF.
            if ($stdin !== null) {
                fwrite($pipes[0], $stdin);
            }

            fclose($pipes[0]);
            unset($pipes[0]); // prevent double-close in finally

            // Note: stream_set_blocking() is unreliable for proc_open pipes on
            // Windows — fread() on a non-blocking pipe still blocks there.
            // We therefore poll only via proc_get_status() and read stdout/stderr
            // in one shot once the process has exited (or been terminated).
            // Output is capped to the OS pipe buffer (~64 KB on Linux); this is
            // intentional and acceptable for the commands this class executes.

            $startTime = time();

            while (true) {
                $status = proc_get_status($process);

                if (!$status['running']) {
                    // Capture exit code on the FIRST call where running = false.
                    // proc_get_status() internally reaps the zombie via waitpid() on
                    // some PHP/OS combinations (notably PHP 8.2 on Linux), making a
                    // subsequent proc_close() return -1.  Reading it here is reliable.
                    $exitCode = $status['exitcode'];
                    $stdout .= stream_get_contents($pipes[1]) ?: '';
                    $stderr .= stream_get_contents($pipes[2]) ?: '';
                    break;
                }

                if (time() - $startTime > $timeout) {
                    proc_terminate($process, 9);
                    $timedOut = true;
                    break;
                }

                usleep(10000); // 10 ms polling
            }
        } finally {
            // Always clean up pipes and the process handle regardless of how we exited.
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            if (is_resource($process)) {
                proc_close($process); // cleanup only — exit code already captured above
            }
        }

        if ($timedOut) {
            return [
                'success' => false,
                'output' => $stdout,
                'stderr' => $stderr,
                'exit_code' => -1,
                'timed_out' => true,
            ];
        }

        return [
            'success' => $exitCode === 0,
            'output' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
        ];
    }

    /**
     * Get the PHP binary path.
     *
     * Resolution order:
     * 1. Explicitly set via setPhpBinary()
     * 2. Configuration key Synapse.tinker.php_binary
     * 3. `which php` command output
     * 4. PHP_BINARY constant
     *
     * @return string|null Absolute path to the PHP binary, or null if undetectable.
     */
    public function getPhpBinary(): ?string
    {
        if ($this->phpBinary !== null) {
            return $this->phpBinary;
        }

        $configured = Configure::read('Synapse.tinker.php_binary');
        if ($configured !== null && is_string($configured) && is_executable($configured)) {
            return $this->phpBinary = $configured;
        }

        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $whichResult = shell_exec('which php 2>' . $nullDevice);
        if (is_string($whichResult) && trim($whichResult) !== '') {
            $which = trim($whichResult);
            if (is_executable($which)) {
                return $this->phpBinary = $which;
            }
        }

        if (is_executable(PHP_BINARY)) {
            return $this->phpBinary = PHP_BINARY;
        }

        return null;
    }

    /**
     * Get the CakePHP bin directory path.
     *
     * Resolution order:
     * 1. Explicitly set via setBinPath()
     * 2. Configuration key Synapse.tinker.bin_path
     * 3. ROOT constant + /bin
     * 4. Current working directory + /bin
     *
     * @return string Absolute path to the bin directory.
     */
    public function getBinPath(): string
    {
        if ($this->binPath !== null) {
            return $this->binPath;
        }

        $configured = Configure::read('Synapse.tinker.bin_path');
        if ($configured !== null && is_string($configured)) {
            return $this->binPath = $configured;
        }

        if (defined('ROOT')) {
            return $this->binPath = ROOT . '/bin';
        }

        return $this->binPath = getcwd() . '/bin';
    }

    /**
     * Override the PHP binary path (useful in tests).
     *
     * @param string|null $path Absolute path to PHP, or null to re-enable auto-detection.
     */
    public function setPhpBinary(?string $path): static
    {
        $this->phpBinary = $path;

        return $this;
    }

    /**
     * Override the bin directory path (useful in tests).
     *
     * @param string|null $path Absolute path to the bin directory, or null to re-enable auto-detection.
     */
    public function setBinPath(?string $path): static
    {
        $this->binPath = $path;

        return $this;
    }
}
