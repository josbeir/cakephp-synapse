<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase;

use RuntimeException;
use Synapse\Documentation\Git\GitAdapter;

/**
 * Mock Git Adapter for Testing
 *
 * Simulates git operations without actually executing git commands.
 */
class MockGitAdapter extends GitAdapter
{
    /**
     * Simulated repositories (path => data)
     *
     * @var array<string, array<string, mixed>>
     */
    private array $repositories = [];

    /**
     * Whether to simulate clone failure
     */
    private bool $simulateCloneFailure = false;

    /**
     * Clone failure message
     */
    private string $cloneFailureMessage = 'Simulated clone failure';

    /**
     * Clone a repository (simulated)
     *
     * @param string $url Repository URL
     * @param string $branch Branch to clone
     * @param string $path Local path to clone to
     * @param bool $shallow Whether to do shallow clone
     * @throws \RuntimeException If clone fails
     */
    public function clone(string $url, string $branch, string $path, bool $shallow = true): void
    {
        if ($this->simulateCloneFailure) {
            throw new RuntimeException($this->cloneFailureMessage);
        }

        // Simulate successful clone by creating repository record
        $this->repositories[$path] = [
            'url' => $url,
            'branch' => $branch,
            'commit' => $this->generateMockCommitHash(),
            'cloned_at' => time(),
        ];

        // Create the .git directory marker
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (!is_dir($path . DIRECTORY_SEPARATOR . '.git')) {
            mkdir($path . DIRECTORY_SEPARATOR . '.git', 0755, true);
        }
    }

    /**
     * Get current commit hash (simulated)
     *
     * @param string $path Repository path
     * @return string|null Commit hash or null on failure
     */
    public function getCurrentCommit(string $path): ?string
    {
        if (!isset($this->repositories[$path])) {
            return null;
        }

        return $this->repositories[$path]['commit'];
    }

    /**
     * Check if git is available (always true in mock)
     */
    public function isGitAvailable(): bool
    {
        return true;
    }

    /**
     * Set whether to simulate clone failure
     *
     * @param bool $fail Whether to fail
     * @param string $message Failure message
     */
    public function setSimulateCloneFailure(bool $fail, string $message = 'Simulated clone failure'): void
    {
        $this->simulateCloneFailure = $fail;
        $this->cloneFailureMessage = $message;
    }

    /**
     * Check if a repository has been cloned
     *
     * @param string $path Repository path
     */
    public function hasRepository(string $path): bool
    {
        return isset($this->repositories[$path]);
    }

    /**
     * Get repository data
     *
     * @param string $path Repository path
     * @return array<string, mixed>|null
     */
    public function getRepositoryData(string $path): ?array
    {
        return $this->repositories[$path] ?? null;
    }

    /**
     * Reset all simulated repositories
     */
    public function reset(): void
    {
        $this->repositories = [];
        $this->simulateCloneFailure = false;
        $this->cloneFailureMessage = 'Simulated clone failure';
    }

    /**
     * Generate a mock commit hash
     */
    private function generateMockCommitHash(): string
    {
        // Generate a 40-character hash (like git commit hashes)
        return md5((string)microtime(true)) . substr(md5(random_bytes(16)), 0, 8);
    }

    /**
     * Manually set a commit hash for a repository (useful for testing)
     *
     * @param string $path Repository path
     * @param string $commit Commit hash
     */
    public function setCommit(string $path, string $commit): void
    {
        if (isset($this->repositories[$path])) {
            $this->repositories[$path]['commit'] = $commit;
        }
    }
}
