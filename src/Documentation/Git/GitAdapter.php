<?php
declare(strict_types=1);

namespace Synapse\Documentation\Git;

use RuntimeException;

/**
 * Git Adapter
 *
 * Encapsulates git command execution for easier testing and mocking.
 */
class GitAdapter
{
    /**
     * Clone a repository
     *
     * @param string $url Repository URL
     * @param string $branch Branch to clone
     * @param string $path Local path to clone to
     * @param bool $shallow Whether to do shallow clone
     * @throws \RuntimeException If clone fails
     */
    public function clone(string $url, string $branch, string $path, bool $shallow = true): void
    {
        $command = sprintf(
            'git clone --depth 1 --branch %s --single-branch %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($url),
            escapeshellarg($path),
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException(sprintf(
                'Git clone failed: %s',
                implode("\n", $output),
            ));
        }
    }

    /**
     * Get current commit hash
     *
     * @param string $path Repository path
     * @return string|null Commit hash or null on failure
     */
    public function getCurrentCommit(string $path): ?string
    {
        $command = sprintf(
            'cd %s && git rev-parse HEAD 2>&1',
            escapeshellarg($path),
        );

        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0 || empty($output[0])) {
            return null;
        }

        return trim($output[0]);
    }

    /**
     * Check if git is available
     */
    public function isGitAvailable(): bool
    {
        $output = [];
        $returnVar = 0;
        exec('git --version 2>&1', $output, $returnVar);

        return $returnVar === 0;
    }
}
