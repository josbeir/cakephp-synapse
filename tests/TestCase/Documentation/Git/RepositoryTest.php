<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Documentation\Git;

use Cake\TestSuite\TestCase;
use RuntimeException;
use Synapse\Documentation\Git\Repository;
use Synapse\Test\TestCase\MockGitAdapter;

/**
 * Repository Test Case
 *
 * Tests for git repository operations using MockGitAdapter.
 */
class RepositoryTest extends TestCase
{
    /**
     * Temporary test directory for git operations
     */
    protected string $testDir;

    /**
     * Mock Git Adapter
     */
    protected MockGitAdapter $gitAdapter;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = TMP . 'test_repos' . DS;
        $this->gitAdapter = new MockGitAdapter();

        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0755, true);
        }
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->gitAdapter->reset();

        // Clean up test directories
        if (is_dir($this->testDir)) {
            $this->removeDirectory($this->testDir);
        }
    }

    /**
     * Recursively remove a directory
     *
     * @param string $dir Directory path
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DS . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Test constructor sets properties correctly
     */
    public function testConstructor(): void
    {
        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: '/path/to/repo',
            root: 'docs',
            gitAdapter: $this->gitAdapter,
        );

        $this->assertEquals('https://github.com/test/repo.git', $repository->url);
        $this->assertEquals('main', $repository->branch);
        $this->assertEquals('/path/to/repo', $repository->path);
        $this->assertEquals('docs', $repository->root);
    }

    /**
     * Test exists returns false for non-existent repository
     */
    public function testExistsReturnsFalseForNonExistent(): void
    {
        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $this->testDir . 'non-existent',
            gitAdapter: $this->gitAdapter,
        );

        $this->assertFalse($repository->exists());
    }

    /**
     * Test exists returns true for existing repository
     */
    public function testExistsReturnsTrueForExisting(): void
    {
        $repoPath = $this->testDir . 'existing-repo';
        mkdir($repoPath, 0755, true);
        mkdir($repoPath . DS . '.git', 0755, true);

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $this->assertTrue($repository->exists());
    }

    /**
     * Test clone creates repository successfully
     */
    public function testCloneCreatesRepository(): void
    {
        $repoPath = $this->testDir . 'new-repo';

        $repository = new Repository(
            url: 'https://github.com/cakephp/docs-md.git',
            branch: '5.x',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $this->assertFalse($repository->exists());

        $repository->clone();

        $this->assertTrue($repository->exists());
        $this->assertTrue($this->gitAdapter->hasRepository($repoPath));
    }

    /**
     * Test clone does nothing if repository already exists
     */
    public function testCloneSkipsIfAlreadyExists(): void
    {
        $repoPath = $this->testDir . 'existing-repo';
        mkdir($repoPath, 0755, true);
        mkdir($repoPath . DS . '.git', 0755, true);

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        // Should not throw exception
        $repository->clone();
        $this->assertTrue($repository->exists());
    }

    /**
     * Test clone creates parent directory if needed
     */
    public function testCloneCreatesParentDirectory(): void
    {
        $repoPath = $this->testDir . 'nested' . DS . 'path' . DS . 'to' . DS . 'repo';

        $this->assertFalse(is_dir(dirname($repoPath)));

        $repository = new Repository(
            url: 'https://github.com/cakephp/docs-md.git',
            branch: '5.x',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $repository->clone();

        $this->assertTrue(is_dir(dirname($repoPath)));
        $this->assertTrue($repository->exists());
    }

    /**
     * Test clone throws exception on failure
     */
    public function testCloneThrowsExceptionOnFailure(): void
    {
        $this->gitAdapter->setSimulateCloneFailure(true, 'Network error');

        $repoPath = $this->testDir . 'failed-repo';

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Network error');

        $repository->clone();
    }

    /**
     * Test getMarkdownFiles returns empty array for non-existent repository
     */
    public function testGetMarkdownFilesReturnsEmptyForNonExistent(): void
    {
        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $this->testDir . 'non-existent',
            gitAdapter: $this->gitAdapter,
        );

        $files = $repository->getMarkdownFiles();
        $this->assertEmpty($files);
    }

    /**
     * Test getMarkdownFiles finds markdown files
     */
    public function testGetMarkdownFilesFindsFiles(): void
    {
        $repoPath = $this->testDir . 'test-repo';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);
        mkdir($repoPath . DS . 'docs' . DS . 'guides', 0755, true);

        // Create some test files
        file_put_contents($repoPath . DS . 'README.md', '# Readme');
        file_put_contents($repoPath . DS . 'docs' . DS . 'intro.md', '# Intro');
        file_put_contents($repoPath . DS . 'docs' . DS . 'guides' . DS . 'guide.md', '# Guide');
        file_put_contents($repoPath . DS . 'docs' . DS . 'script.js', 'console.log("test")');

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $files = $repository->getMarkdownFiles();

        $this->assertContains('README.md', $files);
        $this->assertContains('docs/intro.md', $files);
        $this->assertContains('docs/guides/guide.md', $files);
        $this->assertNotContains('docs/script.js', $files);
    }

    /**
     * Test getMarkdownFiles respects root directory
     */
    public function testGetMarkdownFilesRespectsRoot(): void
    {
        $repoPath = $this->testDir . 'test-repo';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);
        mkdir($repoPath . DS . 'other', 0755, true);

        file_put_contents($repoPath . DS . 'README.md', '# Readme');
        file_put_contents($repoPath . DS . 'docs' . DS . 'intro.md', '# Intro');
        file_put_contents($repoPath . DS . 'other' . DS . 'file.md', '# Other');

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            root: 'docs',
            gitAdapter: $this->gitAdapter,
        );

        $files = $repository->getMarkdownFiles();

        $this->assertContains('docs/intro.md', $files);
        $this->assertNotContains('README.md', $files);
        $this->assertNotContains('other/file.md', $files);
    }

    /**
     * Test getAbsolutePath returns correct absolute path
     */
    public function testGetAbsolutePath(): void
    {
        $repoPath = $this->testDir . 'test-repo';

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $absolutePath = $repository->getAbsolutePath('docs/intro.md');
        $expected = $repoPath . DS . 'docs' . DS . 'intro.md';

        $this->assertEquals($expected, $absolutePath);
    }

    /**
     * Test readFile returns null for non-existent file
     */
    public function testReadFileReturnsNullForNonExistent(): void
    {
        $repoPath = $this->testDir . 'test-repo';
        mkdir($repoPath, 0755, true);

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $content = $repository->readFile('non-existent.md');
        $this->assertNull($content);
    }

    /**
     * Test readFile returns file content
     */
    public function testReadFileReturnsContent(): void
    {
        $repoPath = $this->testDir . 'test-repo';
        mkdir($repoPath, 0755, true);

        $fileContent = '# Test Document' . "\n\n" . 'This is a test.';
        file_put_contents($repoPath . DS . 'test.md', $fileContent);

        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $content = $repository->readFile('test.md');
        $this->assertEquals($fileContent, $content);
    }

    /**
     * Test getCurrentCommit returns null for non-existent repository
     */
    public function testGetCurrentCommitReturnsNullForNonExistent(): void
    {
        $repository = new Repository(
            url: 'https://github.com/test/repo.git',
            branch: 'main',
            path: $this->testDir . 'non-existent',
            gitAdapter: $this->gitAdapter,
        );

        $commit = $repository->getCurrentCommit();
        $this->assertNull($commit);
    }

    /**
     * Test getCurrentCommit returns commit hash after clone
     */
    public function testGetCurrentCommitReturnsHashAfterClone(): void
    {
        $repoPath = $this->testDir . 'test-repo';

        $repository = new Repository(
            url: 'https://github.com/cakephp/docs-md.git',
            branch: '5.x',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $repository->clone();

        $commit = $repository->getCurrentCommit();
        $this->assertNotNull($commit);
        $this->assertEquals(40, strlen($commit)); // Git commit hashes are 40 chars
    }

    /**
     * Test getCurrentCommit returns consistent hash
     */
    public function testGetCurrentCommitReturnsConsistentHash(): void
    {
        $repoPath = $this->testDir . 'test-repo';

        $repository = new Repository(
            url: 'https://github.com/cakephp/docs-md.git',
            branch: '5.x',
            path: $repoPath,
            gitAdapter: $this->gitAdapter,
        );

        $repository->clone();

        $commit1 = $repository->getCurrentCommit();
        $commit2 = $repository->getCurrentCommit();

        $this->assertEquals($commit1, $commit2);
    }
}
