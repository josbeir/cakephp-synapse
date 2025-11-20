<?php
declare(strict_types=1);

namespace Synapse\Documentation\Git;

/**
 * Git Adapter Interface
 *
 * Defines the contract for git operations used in documentation management.
 */
interface GitAdapterInterface
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
    public function clone(string $url, string $branch, string $path, bool $shallow = true): void;

    /**
     * Pull latest changes from remote
     *
     * @param string $path Repository path
     * @param string $branch Branch to pull
     * @throws \RuntimeException If pull fails
     */
    public function pull(string $path, string $branch): void;

    /**
     * Get current commit hash
     *
     * @param string $path Repository path
     * @return string|null Commit hash or null on failure
     */
    public function getCurrentCommit(string $path): ?string;

    /**
     * Check if git is available
     *
     * @return bool True if git is available, false otherwise
     */
    public function isGitAvailable(): bool;
}
