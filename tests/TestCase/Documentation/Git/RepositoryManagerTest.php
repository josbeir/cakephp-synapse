<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Documentation\Git;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use RuntimeException;
use Synapse\Documentation\Git\Repository;
use Synapse\Documentation\Git\RepositoryManager;
use Synapse\TestSuite\MockGitAdapter;

/**
 * RepositoryManager Test Case
 *
 * Tests for repository management functionality.
 */
class RepositoryManagerTest extends TestCase
{
    private string $testCacheDir;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testCacheDir = TMP . 'tests' . DS . 'repo_manager_' . uniqid();
        if (!is_dir($this->testCacheDir)) {
            mkdir($this->testCacheDir, 0755, true);
        }

        // Configure test settings
        // Configure test settings
        Configure::write('Synapse.documentation', [
            'git_adapter' => MockGitAdapter::class,
            'cache_dir' => $this->testCacheDir,
            'sources' => [],
        ]);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test cache directory
        if (is_dir($this->testCacheDir)) {
            $this->removeDirectory($this->testCacheDir);
        }
    }

    /**
     * Test constructor with default values uses Configure
     */
    public function testConstructorWithDefaults(): void
    {
        $manager = new RepositoryManager();

        $this->assertEquals($this->testCacheDir, $manager->getCacheDir());
    }

    /**
     * Test constructor with explicit values
     */
    public function testConstructorWithExplicitValues(): void
    {
        $customDir = TMP . 'custom_cache';
        $sources = ['test' => ['enabled' => true, 'repository' => 'test', 'branch' => 'main']];

        $manager = new RepositoryManager($customDir, $sources);

        $this->assertEquals($customDir, $manager->getCacheDir());
        $this->assertEquals(['test'], $manager->getEnabledSources());
    }

    /**
     * Test getRepository returns cached instance
     */
    public function testGetRepositoryReturnsCachedInstance(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);

        $repo1 = $manager->getRepository('test-source');
        $repo2 = $manager->getRepository('test-source');

        $this->assertInstanceOf(Repository::class, $repo1);
        $this->assertSame($repo1, $repo2, 'Should return same cached instance');
    }

    /**
     * Test getRepository throws exception for unconfigured source
     */
    public function testGetRepositoryThrowsExceptionForUnconfiguredSource(): void
    {
        $manager = new RepositoryManager($this->testCacheDir, []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Documentation source "nonexistent" is not configured');

        $manager->getRepository('nonexistent');
    }

    /**
     * Test getRepository throws exception for disabled source
     */
    public function testGetRepositoryThrowsExceptionForDisabledSource(): void
    {
        $sources = [
            'disabled-source' => [
                'enabled' => false,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Documentation source "disabled-source" is disabled');

        $manager->getRepository('disabled-source');
    }

    /**
     * Test getAllRepositories returns only enabled repositories
     */
    public function testGetAllRepositoriesReturnsOnlyEnabled(): void
    {
        $sources = [
            'enabled-1' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo1.git',
                'branch' => 'main',
            ],
            'disabled' => [
                'enabled' => false,
                'repository' => 'https://github.com/test/repo2.git',
                'branch' => 'main',
            ],
            'enabled-2' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo3.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $repositories = $manager->getAllRepositories();

        $this->assertCount(2, $repositories);
        $this->assertArrayHasKey('enabled-1', $repositories);
        $this->assertArrayHasKey('enabled-2', $repositories);
        $this->assertArrayNotHasKey('disabled', $repositories);
    }

    /**
     * Test initialize returns true when repository is cloned
     */
    public function testInitializeReturnsTrueWhenCloned(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $result = $manager->initialize('test-source');

        $this->assertTrue($result, 'Should return true when repository was cloned');
        $this->assertTrue($manager->exists('test-source'));
    }

    /**
     * Test initialize returns false when repository already exists
     */
    public function testInitializeReturnsFalseWhenExists(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);

        // First call should clone
        $manager->initialize('test-source');

        // Second call should return false
        $result = $manager->initialize('test-source');

        $this->assertFalse($result, 'Should return false when repository already exists');
    }

    /**
     * Test initializeAll initializes all enabled repositories
     */
    public function testInitializeAllInitializesEnabledRepositories(): void
    {
        $sources = [
            'source-1' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo1.git',
                'branch' => 'main',
            ],
            'source-2' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo2.git',
                'branch' => 'main',
            ],
            'disabled' => [
                'enabled' => false,
                'repository' => 'https://github.com/test/repo3.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $results = $manager->initializeAll();

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('source-1', $results);
        $this->assertArrayHasKey('source-2', $results);
        $this->assertTrue($results['source-1']);
        $this->assertTrue($results['source-2']);
    }

    /**
     * Test exists returns false for nonexistent repository
     */
    public function testExistsReturnsFalseForNonexistent(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);

        $this->assertFalse($manager->exists('test-source'));
    }

    /**
     * Test exists returns true after initialization
     */
    public function testExistsReturnsTrueAfterInitialization(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $manager->initialize('test-source');

        $this->assertTrue($manager->exists('test-source'));
    }

    /**
     * Test exists returns false for unconfigured source
     */
    public function testExistsReturnsFalseForUnconfiguredSource(): void
    {
        $manager = new RepositoryManager($this->testCacheDir, []);

        $this->assertFalse($manager->exists('nonexistent'));
    }

    /**
     * Test getEnabledSources returns correct list
     */
    public function testGetEnabledSourcesReturnsCorrectList(): void
    {
        $sources = [
            'enabled-1' => ['enabled' => true, 'repository' => 'test', 'branch' => 'main'],
            'disabled' => ['enabled' => false, 'repository' => 'test', 'branch' => 'main'],
            'enabled-2' => ['enabled' => true, 'repository' => 'test', 'branch' => 'main'],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $enabled = $manager->getEnabledSources();

        $this->assertCount(2, $enabled);
        $this->assertContains('enabled-1', $enabled);
        $this->assertContains('enabled-2', $enabled);
        $this->assertNotContains('disabled', $enabled);
    }

    /**
     * Test getSourceConfig returns correct configuration
     */
    public function testGetSourceConfigReturnsCorrectConfiguration(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $config = $manager->getSourceConfig('test-source');

        $this->assertEquals('https://github.com/test/repo.git', $config['repository']);
        $this->assertEquals('main', $config['branch']);
        $this->assertEquals('docs', $config['root']);
    }

    /**
     * Test getSourceConfig throws exception for missing required fields
     */
    public function testGetSourceConfigThrowsExceptionForMissingFields(): void
    {
        $sources = [
            'invalid-source' => [
                'enabled' => true,
                // Missing repository and branch
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is missing required field: repository');

        $manager->getSourceConfig('invalid-source');
    }

    /**
     * Test getMetadata returns correct metadata
     */
    public function testGetMetadataReturnsCorrectMetadata(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'metadata' => [
                    'name' => 'Test Docs',
                    'version' => '1.0',
                ],
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $metadata = $manager->getMetadata('test-source');

        $this->assertEquals('Test Docs', $metadata['name']);
        $this->assertEquals('1.0', $metadata['version']);
    }

    /**
     * Test getMetadata returns empty array when no metadata
     */
    public function testGetMetadataReturnsEmptyArrayWhenNoMetadata(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $metadata = $manager->getMetadata('test-source');

        $this->assertEmpty($metadata);
    }

    /**
     * Test getRepositoryPath returns correct path
     */
    public function testGetRepositoryPathReturnsCorrectPath(): void
    {
        $manager = new RepositoryManager($this->testCacheDir, []);
        $path = $manager->getRepositoryPath('test-source');

        $expected = $this->testCacheDir . DS . 'test-source';
        $this->assertEquals($expected, $path);
    }

    /**
     * Test repository with root directory
     */
    public function testRepositoryWithRootDirectory(): void
    {
        $sources = [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs/en',
            ],
        ];

        $manager = new RepositoryManager($this->testCacheDir, $sources);
        $repository = $manager->getRepository('test-source');

        $this->assertInstanceOf(Repository::class, $repository);
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
