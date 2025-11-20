<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use RuntimeException;
use Synapse\TestSuite\MockGitAdapter;

/**
 * MockGitAdapter Test Case
 *
 * Tests for mock git adapter functionality.
 */
class MockGitAdapterTest extends TestCase
{
    private MockGitAdapter $adapter;

    private string $testPath;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = new MockGitAdapter();
        $this->testPath = TMP . 'tests' . DS . 'mock_git_' . uniqid();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test directories
        if (is_dir($this->testPath)) {
            $this->removeDirectory($this->testPath);
        }

        $this->adapter->reset();
    }

    /**
     * Test clone creates repository record
     */
    public function testCloneCreatesRepositoryRecord(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);

        $this->assertTrue($this->adapter->hasRepository($this->testPath));
        $this->assertDirectoryExists($this->testPath . DS . '.git');
    }

    /**
     * Test clone with shallow option
     */
    public function testCloneWithShallowOption(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath, true);

        $this->assertTrue($this->adapter->hasRepository($this->testPath));
    }

    /**
     * Test clone stores repository data
     */
    public function testCloneStoresRepositoryData(): void
    {
        $url = 'https://github.com/test/repo.git';
        $branch = 'develop';

        $this->adapter->clone($url, $branch, $this->testPath);

        $data = $this->adapter->getRepositoryData($this->testPath);

        $this->assertIsArray($data);
        $this->assertEquals($url, $data['url']);
        $this->assertEquals($branch, $data['branch']);
        $this->assertArrayHasKey('commit', $data);
        $this->assertArrayHasKey('cloned_at', $data);
    }

    /**
     * Test getCurrentCommit returns commit hash
     */
    public function testGetCurrentCommitReturnsCommitHash(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);

        $commit = $this->adapter->getCurrentCommit($this->testPath);

        $this->assertIsString($commit);
        $this->assertEquals(40, strlen($commit));
    }

    /**
     * Test getCurrentCommit returns null for nonexistent repository
     */
    public function testGetCurrentCommitReturnsNullForNonexistent(): void
    {
        $commit = $this->adapter->getCurrentCommit('/nonexistent/path');

        $this->assertNull($commit);
    }

    /**
     * Test pull updates repository
     */
    public function testPullUpdatesRepository(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
        $commitBefore = $this->adapter->getCurrentCommit($this->testPath);

        $this->adapter->pull($this->testPath, 'main');
        $commitAfter = $this->adapter->getCurrentCommit($this->testPath);

        $this->assertNotEquals($commitBefore, $commitAfter, 'Commit hash should change after pull');
    }

    /**
     * Test pull throws exception for nonexistent repository
     */
    public function testPullThrowsExceptionForNonexistentRepository(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot pull repository that does not exist');

        $this->adapter->pull('/nonexistent/path', 'main');
    }

    /**
     * Test pull tracks operations
     */
    public function testPullTracksOperations(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);

        $this->assertEquals(0, $this->adapter->getPullCount($this->testPath));
        $this->assertFalse($this->adapter->wasPulled($this->testPath));

        $this->adapter->pull($this->testPath, 'main');

        $this->assertEquals(1, $this->adapter->getPullCount($this->testPath));
        $this->assertTrue($this->adapter->wasPulled($this->testPath));

        $this->adapter->pull($this->testPath, 'main');

        $this->assertEquals(2, $this->adapter->getPullCount($this->testPath));
    }

    /**
     * Test isGitAvailable always returns true
     */
    public function testIsGitAvailableAlwaysReturnsTrue(): void
    {
        $this->assertTrue($this->adapter->isGitAvailable());
    }

    /**
     * Test setSimulateCloneFailure causes clone to fail
     */
    public function testSetSimulateCloneFailureCausesCloneToFail(): void
    {
        $this->adapter->setSimulateCloneFailure(true, 'Test failure');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Test failure');

        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
    }

    /**
     * Test setSimulatePullFailure causes pull to fail
     */
    public function testSetSimulatePullFailureCausesPullToFail(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
        $this->adapter->setSimulatePullFailure(true, 'Pull failure');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pull failure');

        $this->adapter->pull($this->testPath, 'main');
    }

    /**
     * Test hasRepository returns false for nonexistent repository
     */
    public function testHasRepositoryReturnsFalseForNonexistent(): void
    {
        $this->assertFalse($this->adapter->hasRepository($this->testPath));
    }

    /**
     * Test getRepositoryData returns null for nonexistent repository
     */
    public function testGetRepositoryDataReturnsNullForNonexistent(): void
    {
        $data = $this->adapter->getRepositoryData($this->testPath);

        $this->assertNull($data);
    }

    /**
     * Test reset clears all repositories
     */
    public function testResetClearsAllRepositories(): void
    {
        $path1 = $this->testPath . '1';
        $path2 = $this->testPath . '2';

        $this->adapter->clone('https://github.com/test/repo1.git', 'main', $path1);
        $this->adapter->clone('https://github.com/test/repo2.git', 'main', $path2);

        $this->assertTrue($this->adapter->hasRepository($path1));
        $this->assertTrue($this->adapter->hasRepository($path2));

        $this->adapter->reset();

        $this->assertFalse($this->adapter->hasRepository($path1));
        $this->assertFalse($this->adapter->hasRepository($path2));
    }

    /**
     * Test reset clears pull operations
     */
    public function testResetClearsPullOperations(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
        $this->adapter->pull($this->testPath, 'main');

        $this->assertEquals(1, $this->adapter->getPullCount($this->testPath));

        $this->adapter->reset();

        $this->assertEquals(0, $this->adapter->getPullCount($this->testPath));
    }

    /**
     * Test reset clears failure simulations
     */
    public function testResetClearsFailureSimulations(): void
    {
        $this->adapter->setSimulateCloneFailure(true);
        $this->adapter->setSimulatePullFailure(true);

        $this->adapter->reset();

        // Clone should succeed after reset
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
        $this->assertTrue($this->adapter->hasRepository($this->testPath));
    }

    /**
     * Test setCommit manually sets commit hash
     */
    public function testSetCommitManuallySetsCommitHash(): void
    {
        $this->adapter->clone('https://github.com/test/repo.git', 'main', $this->testPath);
        $customCommit = 'abcdef1234567890abcdef1234567890abcdef12';

        $this->adapter->setCommit($this->testPath, $customCommit);

        $this->assertEquals($customCommit, $this->adapter->getCurrentCommit($this->testPath));
    }

    /**
     * Test setCommit does nothing for nonexistent repository
     */
    public function testSetCommitDoesNothingForNonexistentRepository(): void
    {
        $this->adapter->setCommit($this->testPath, 'abc123');

        $this->assertNull($this->adapter->getCurrentCommit($this->testPath));
    }

    /**
     * Test getPullCount returns zero for unpulled repository
     */
    public function testGetPullCountReturnsZeroForUnpulledRepository(): void
    {
        $this->assertEquals(0, $this->adapter->getPullCount('/nonexistent/path'));
    }

    /**
     * Test wasPulled returns false for unpulled repository
     */
    public function testWasPulledReturnsFalseForUnpulledRepository(): void
    {
        $this->assertFalse($this->adapter->wasPulled('/nonexistent/path'));
    }

    /**
     * Recursively remove directory
     *
     * @param string $dir Directory to remove
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DS . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        rmdir($dir);
    }
}
