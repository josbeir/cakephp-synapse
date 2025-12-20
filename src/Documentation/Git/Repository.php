<?php
declare(strict_types=1);

namespace Synapse\Documentation\Git;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Represents a git repository for documentation
 *
 * Handles shallow cloning of a documentation repository.
 */
class Repository
{
    /**
     * Constructor
     *
     * @param string $url Git repository URL
     * @param string $branch Branch to checkout
     * @param string $path Local path where repository is/will be cloned
     * @param string $root Root directory within repo to work with (relative to repo root)
     * @param \Synapse\Documentation\Git\GitAdapterInterface|null $gitAdapter Git adapter for operations
     */
    public function __construct(
        public readonly string $url,
        public readonly string $branch,
        public readonly string $path,
        public readonly string $root = '',
        ?GitAdapterInterface $gitAdapter = null,
    ) {
        $this->gitAdapter = $gitAdapter ?? new GitAdapter();
    }

    /**
     * @var \Synapse\Documentation\Git\GitAdapterInterface Git adapter for operations
     */
    private GitAdapterInterface $gitAdapter;

    /**
     * Check if repository exists locally
     */
    public function exists(): bool
    {
        return is_dir($this->path . DS . '.git');
    }

    /**
     * Clone the repository (shallow, no history)
     *
     * @throws \RuntimeException If clone fails
     */
    public function clone(): void
    {
        if ($this->exists()) {
            return; // Already cloned
        }

        // Ensure parent directory exists
        $parentDir = dirname($this->path);
        if (!is_dir($parentDir) && !mkdir($parentDir, 0755, true)) {
            throw new RuntimeException(sprintf(
                'Failed to create directory: %s',
                $parentDir,
            ));
        }

        // Delegate to git adapter
        $this->gitAdapter->clone($this->url, $this->branch, $this->path);
    }

    /**
     * Pull latest changes from remote
     *
     * Updates the repository to the latest version of the configured branch.
     *
     * @throws \RuntimeException If pull fails or repository doesn't exist
     */
    public function pull(): void
    {
        if (!$this->exists()) {
            throw new RuntimeException(sprintf(
                'Cannot pull repository that does not exist: %s',
                $this->path,
            ));
        }

        $this->gitAdapter->pull($this->path, $this->branch);
    }

    /**
     * Get all markdown files in the repository
     *
     * @return array<string> List of markdown file paths relative to repository root
     */
    public function getMarkdownFiles(): array
    {
        if (!$this->exists()) {
            return [];
        }

        $searchPath = $this->path;
        if ($this->root !== '') {
            $searchPath .= DS . str_replace('/', DS, $this->root);
        }

        if (!is_dir($searchPath)) {
            return [];
        }

        $files = [];
        $pathPrefix = $this->path . DS;
        $pathPrefixLen = strlen($pathPrefix);

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $searchPath,
                    RecursiveDirectoryIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'md') {
                    $absolutePath = $file->getPathname();
                    if (str_starts_with($absolutePath, $pathPrefix)) {
                        $relativePath = substr($absolutePath, $pathPrefixLen);
                        $relativePath = str_replace(DS, '/', $relativePath);
                        $files[] = $relativePath;
                    }
                }
            }
        } catch (Exception $e) {
            return [];
        }

        return $files;
    }

    /**
     * Get absolute path to a file in the repository
     *
     * @param string $relativePath Path relative to repository root
     * @return string Absolute path
     */
    public function getAbsolutePath(string $relativePath): string
    {
        $path = str_replace('/', DS, $relativePath);

        return $this->path . DS . $path;
    }

    /**
     * Read file content
     *
     * @param string $relativePath Path relative to repository root
     * @return string|null File content or null if file doesn't exist
     */
    public function readFile(string $relativePath): ?string
    {
        $absolutePath = $this->getAbsolutePath($relativePath);

        if (!file_exists($absolutePath)) {
            return null;
        }

        $content = file_get_contents($absolutePath);

        return $content !== false ? $content : null;
    }

    /**
     * Get current commit hash
     *
     * @return string|null Commit hash or null on failure
     */
    public function getCurrentCommit(): ?string
    {
        if (!$this->exists()) {
            return null;
        }

        return $this->gitAdapter->getCurrentCommit($this->path);
    }
}
