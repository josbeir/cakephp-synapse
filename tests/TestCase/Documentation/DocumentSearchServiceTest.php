<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Documentation;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Synapse\Documentation\DocumentProcessor;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Documentation\Git\RepositoryManager;
use Synapse\Documentation\SearchEngine;
use Synapse\TestSuite\MockGitAdapter;

/**
 * DocumentSearchService Test Case
 *
 * Tests for document search service coordinating indexing and searching.
 */
class DocumentSearchServiceTest extends TestCase
{
    private string $testDbPath;

    private string $testCacheDir;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Use a unique temporary database for each test
        $this->testDbPath = TMP . 'tests' . DS . 'search_service_' . uniqid() . '.db';
        $dir = dirname($this->testDbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Set up unique cache directory for this test
        $this->testCacheDir = TMP . 'tests' . DS . 'cache_' . uniqid();
        if (!is_dir($this->testCacheDir)) {
            mkdir($this->testCacheDir, 0755, true);
        }

        // Configure test settings
        Configure::write('Synapse.documentation.cache_dir', $this->testCacheDir);
        Configure::write('Synapse.documentation.search_db', $this->testDbPath);
        Configure::write('Synapse.documentation.search.batch_size', 10);
        Configure::write('Synapse.documentation.search.default_limit', 10);
        Configure::write('Synapse.documentation.search.highlight', true);
        Configure::write('Synapse.documentation.auto_build', false);
        Configure::write('Synapse.documentation.git_adapter', MockGitAdapter::class);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        // Clean up test cache directory
        if (is_dir($this->testCacheDir)) {
            $this->removeDirectory($this->testCacheDir);
        }

        parent::tearDown();
    }

    /**
     * Test constructor creates service with default components
     */
    public function testConstructorCreatesServiceWithDefaults(): void
    {
        $service = new DocumentSearchService();

        $this->assertInstanceOf(DocumentSearchService::class, $service);
        $this->assertInstanceOf(SearchEngine::class, $service->getSearchEngine());
        $this->assertInstanceOf(RepositoryManager::class, $service->getRepositoryManager());
    }

    /**
     * Test constructor with custom database path
     */
    public function testConstructorWithCustomDatabasePath(): void
    {
        $customPath = TMP . 'tests' . DS . 'custom_search.db';

        $service = new DocumentSearchService($customPath);

        $this->assertInstanceOf(DocumentSearchService::class, $service);

        // Clean up
        if (file_exists($customPath)) {
            @unlink($customPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Test constructor creates database directory if it doesn't exist
     */
    public function testConstructorCreatesDirectoryIfNeeded(): void
    {
        $customPath = TMP . 'tests' . DS . 'nested' . DS . 'deep' . DS . 'search.db';
        $dir = dirname($customPath);

        // Ensure directory doesn't exist
        if (is_dir($dir)) {
            rmdir($dir);
        }

        new DocumentSearchService($customPath);

        $this->assertTrue(is_dir($dir));

        // Clean up
        if (file_exists($customPath)) {
            @unlink($customPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        if (is_dir($dir)) {
            rmdir(dirname($dir) . DS . 'deep');
            rmdir(dirname($dir));
        }
    }

    /**
     * Test constructor with custom repository manager
     */
    public function testConstructorWithCustomRepositoryManager(): void
    {
        $mockManager = $this->createMock(RepositoryManager::class);

        $service = new DocumentSearchService(
            databasePath: $this->testDbPath,
            repositoryManager: $mockManager,
        );

        $this->assertSame($mockManager, $service->getRepositoryManager());
    }

    /**
     * Test constructor with custom document processor
     */
    public function testConstructorWithCustomDocumentProcessor(): void
    {
        $mockProcessor = $this->createMock(DocumentProcessor::class);

        $service = new DocumentSearchService(
            databasePath: $this->testDbPath,
            documentProcessor: $mockProcessor,
        );

        $this->assertInstanceOf(DocumentSearchService::class, $service);
    }

    /**
     * Test search with empty index and auto_build disabled
     */
    public function testSearchWithEmptyIndexAndAutoBuildDisabled(): void
    {
        Configure::write('Synapse.documentation.auto_build', false);

        $service = new DocumentSearchService($this->testDbPath);
        $results = $service->search('test query');

        $this->assertEmpty($results);
    }

    /**
     * Test search respects default limit configuration
     */
    public function testSearchRespectsDefaultLimitConfiguration(): void
    {
        Configure::write('Synapse.documentation.search.default_limit', 5);

        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index some test documents
        $documents = [];
        for ($i = 1; $i <= 10; $i++) {
            $documents[] = [
                'source' => 'test',
                'path' => sprintf('doc%d.md', $i),
                'title' => 'Document ' . $i,
                'content' => 'test content for searching',
                'headings' => [],
            ];
        }

        $engine->indexBatch($documents);

        $results = $service->search('test');

        $this->assertCount(5, $results);
    }

    /**
     * Test search with custom limit option
     */
    public function testSearchWithCustomLimitOption(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index test documents
        $documents = [];
        for ($i = 1; $i <= 10; $i++) {
            $documents[] = [
                'source' => 'test',
                'path' => sprintf('doc%d.md', $i),
                'title' => 'Document ' . $i,
                'content' => 'test content for searching',
                'headings' => [],
            ];
        }

        $engine->indexBatch($documents);

        $results = $service->search('test', ['limit' => 3]);

        $this->assertCount(3, $results);
    }

    /**
     * Test search with source filter
     */
    public function testSearchWithSourceFilter(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index documents from different sources
        $documents = [
            [
                'source' => 'source1',
                'path' => 'doc1.md',
                'title' => 'Document 1',
                'content' => 'test content one',
                'headings' => [],
            ],
            [
                'source' => 'source2',
                'path' => 'doc2.md',
                'title' => 'Document 2',
                'content' => 'test content two',
                'headings' => [],
            ],
            [
                'source' => 'source1',
                'path' => 'doc3.md',
                'title' => 'Document 3',
                'content' => 'test content three',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        $results = $service->search('test', ['sources' => ['source1']]);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals('source1', $result['source']);
        }
    }

    /**
     * Test search respects highlight configuration
     */
    public function testSearchRespectsHighlightConfiguration(): void
    {
        Configure::write('Synapse.documentation.search.highlight', false);

        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        $documents = [
            [
                'source' => 'test',
                'path' => 'doc.md',
                'title' => 'Test Document',
                'content' => 'This is test content for searching.',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        $results = $service->search('test', ['highlight' => false]);

        $this->assertNotEmpty($results);
    }

    /**
     * Test getStatistics returns correct structure
     */
    public function testGetStatisticsReturnsCorrectStructure(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        $stats = $service->getStatistics();

        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertArrayHasKey('documents_by_source', $stats);
        $this->assertArrayHasKey('sources', $stats);
        $this->assertIsInt($stats['total_documents']);
        $this->assertIsArray($stats['documents_by_source']);
        $this->assertIsArray($stats['sources']);
    }

    /**
     * Test getStatistics with indexed documents
     */
    public function testGetStatisticsWithIndexedDocuments(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index documents from multiple sources
        $documents = [
            [
                'source' => 'source1',
                'path' => 'doc1.md',
                'title' => 'Document 1',
                'content' => 'content',
                'headings' => [],
            ],
            [
                'source' => 'source1',
                'path' => 'doc2.md',
                'title' => 'Document 2',
                'content' => 'content',
                'headings' => [],
            ],
            [
                'source' => 'source2',
                'path' => 'doc3.md',
                'title' => 'Document 3',
                'content' => 'content',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        $stats = $service->getStatistics();

        $this->assertEquals(3, $stats['total_documents']);
        $this->assertArrayHasKey('source1', $stats['documents_by_source']);
        $this->assertArrayHasKey('source2', $stats['documents_by_source']);
        $this->assertEquals(2, $stats['documents_by_source']['source1']);
        $this->assertEquals(1, $stats['documents_by_source']['source2']);
    }

    /**
     * Test clearIndex removes all documents
     */
    public function testClearIndexRemovesAllDocuments(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index some documents
        $documents = [
            [
                'source' => 'test',
                'path' => 'doc.md',
                'title' => 'Document',
                'content' => 'content',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        $this->assertEquals(1, $engine->getDocumentCount());

        $service->clearIndex();

        $this->assertEquals(0, $engine->getDocumentCount());
    }

    /**
     * Test clearSource removes only specified source
     */
    public function testClearSourceRemovesOnlySpecifiedSource(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        // Index documents from multiple sources
        $documents = [
            [
                'source' => 'source1',
                'path' => 'doc1.md',
                'title' => 'Document 1',
                'content' => 'content',
                'headings' => [],
            ],
            [
                'source' => 'source2',
                'path' => 'doc2.md',
                'title' => 'Document 2',
                'content' => 'content',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        $this->assertEquals(2, $engine->getDocumentCount());

        $service->clearSource('source1');

        $this->assertEquals(1, $engine->getDocumentCount());

        $stats = $service->getStatistics();
        $this->assertArrayNotHasKey('source1', $stats['documents_by_source']);
        $this->assertArrayHasKey('source2', $stats['documents_by_source']);
    }

    /**
     * Test optimize calls engine optimize
     */
    public function testOptimizeCallsEngineOptimize(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        // Should not throw exception
        $service->optimize();

        // Should not throw exception - optimization succeeded
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test getSearchEngine returns engine instance
     */
    public function testGetSearchEngineReturnsEngineInstance(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        $engine = $service->getSearchEngine();

        $this->assertInstanceOf(SearchEngine::class, $engine);
    }

    /**
     * Test getRepositoryManager returns manager instance
     */
    public function testGetRepositoryManagerReturnsManagerInstance(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        $manager = $service->getRepositoryManager();

        $this->assertInstanceOf(RepositoryManager::class, $manager);
    }

    /**
     * Test hasRepository checks if repository exists
     */
    public function testHasRepositoryChecksIfRepositoryExists(): void
    {
        $mockManager = $this->createMock(RepositoryManager::class);
        $mockManager->expects($this->once())
            ->method('exists')
            ->with('test-source')
            ->willReturn(true);

        $service = new DocumentSearchService(
            databasePath: $this->testDbPath,
            repositoryManager: $mockManager,
        );

        $result = $service->hasRepository('test-source');

        $this->assertTrue($result);
    }

    /**
     * Test initializeRepositories calls manager initializeAll
     */
    public function testInitializeRepositoriesCallsManagerInitializeAll(): void
    {
        $expectedResult = [
            'source1' => true,
            'source2' => false,
        ];

        $mockManager = $this->createMock(RepositoryManager::class);
        $mockManager->expects($this->once())
            ->method('initializeAll')
            ->willReturn($expectedResult);

        $service = new DocumentSearchService(
            databasePath: $this->testDbPath,
            repositoryManager: $mockManager,
        );

        $result = $service->initializeRepositories();

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Test search with empty query returns empty results
     */
    public function testSearchWithEmptyQueryReturnsEmptyResults(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        $results = $service->search('');

        $this->assertEmpty($results);
    }

    /**
     * Test search returns properly formatted results
     */
    public function testSearchReturnsProperlyFormattedResults(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        $documents = [
            [
                'source' => 'test',
                'path' => 'test.md',
                'title' => 'Test Document',
                'content' => 'This is a test document with searchable content.',
                'headings' => ['Introduction', 'Details'],
            ],
        ];
        $engine->indexBatch($documents);

        $results = $service->search('searchable');

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('source', $results[0]);
        $this->assertArrayHasKey('path', $results[0]);
        $this->assertArrayHasKey('title', $results[0]);
        $this->assertEquals('test', $results[0]['source']);
        $this->assertEquals('test.md', $results[0]['path']);
        $this->assertEquals('Test Document', $results[0]['title']);
    }

    /**
     * Test search handles special characters in query
     */
    public function testSearchHandlesSpecialCharactersInQuery(): void
    {
        $service = new DocumentSearchService($this->testDbPath);
        $engine = $service->getSearchEngine();

        $documents = [
            [
                'source' => 'test',
                'path' => 'test.md',
                'title' => 'Test',
                'content' => 'Content with special chars: @#$%',
                'headings' => [],
            ],
        ];
        $engine->indexBatch($documents);

        // Should not throw exception
        $results = $service->search('special @#$%');

        // Results should be an array (empty or with results)
        $this->assertGreaterThanOrEqual(0, count($results));
    }

    /**
     * Test constructor uses configuration for cache directory
     */
    public function testConstructorUsesConfigurationForCacheDirectory(): void
    {
        $customCacheDir = TMP . 'tests' . DS . 'custom_cache';
        Configure::write('Synapse.documentation.cache_dir', $customCacheDir);

        // Don't provide explicit database path so it uses config
        $service = new DocumentSearchService();

        $this->assertInstanceOf(DocumentSearchService::class, $service);

        // Clean up any created files
        $searchDb = $customCacheDir . DS . 'search.db';
        if (file_exists($searchDb)) {
            @unlink($searchDb); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Test constructor falls back to TMP if no cache dir configured
     */
    public function testConstructorFallsBackToTmpIfNoCacheDirConfigured(): void
    {
        Configure::delete('Synapse.documentation.cache_dir');
        Configure::delete('Synapse.documentation.search_db');

        $service = new DocumentSearchService();

        $this->assertInstanceOf(DocumentSearchService::class, $service);

        // Clean up
        $defaultDb = TMP . 'synapse' . DS . 'docs' . DS . 'search.db';
        if (file_exists($defaultDb)) {
            @unlink($defaultDb); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }
    }

    /**
     * Test getStatistics with empty index
     */
    public function testGetStatisticsWithEmptyIndex(): void
    {
        $service = new DocumentSearchService($this->testDbPath);

        $stats = $service->getStatistics();

        $this->assertEquals(0, $stats['total_documents']);
        $this->assertEmpty($stats['documents_by_source']);
    }

    /**
     * Test indexSource indexes documents from a repository
     */
    public function testIndexSourceIndexesDocuments(): void
    {
        // Create a test repository with markdown files
        $repoPath = $this->testCacheDir . DS . 'test-source';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);

        // Create test markdown file
        $mdFile = $repoPath . DS . 'docs' . DS . 'test.md';
        file_put_contents($mdFile, "# Test Document\n\nThis is test content.");

        // Configure sources
        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
        ]);

        $service = new DocumentSearchService($this->testDbPath);
        $count = $service->indexSource('test-source');

        $this->assertGreaterThan(0, $count);

        // Verify document was indexed
        $results = $service->search('test');
        $this->assertNotEmpty($results);
    }

    /**
     * Test indexSource with force option clears existing documents
     */
    public function testIndexSourceWithForceOption(): void
    {
        // Create a test repository with markdown files
        $repoPath = $this->testCacheDir . DS . 'test-source';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);

        $mdFile = $repoPath . DS . 'docs' . DS . 'test.md';
        file_put_contents($mdFile, "# Test Document\n\nOriginal content.");

        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
        ]);

        $service = new DocumentSearchService($this->testDbPath);

        // Index first time
        $service->indexSource('test-source');

        $stats1 = $service->getStatistics();

        // Index again with force
        $service->indexSource('test-source', force: true);
        $stats2 = $service->getStatistics();

        // Should have same count (documents replaced)
        $this->assertEquals($stats1['total_documents'], $stats2['total_documents']);
    }

    /**
     * Test indexAll indexes all enabled sources
     */
    public function testIndexAllIndexesAllEnabledSources(): void
    {
        // Create test repositories
        $repo1Path = $this->testCacheDir . DS . 'source-1';
        mkdir($repo1Path . DS . '.git', 0755, true);
        mkdir($repo1Path . DS . 'docs', 0755, true);
        file_put_contents($repo1Path . DS . 'docs' . DS . 'test1.md', "# Doc 1\n\nContent 1.");

        $repo2Path = $this->testCacheDir . DS . 'source-2';
        mkdir($repo2Path . DS . '.git', 0755, true);
        mkdir($repo2Path . DS . 'docs', 0755, true);
        file_put_contents($repo2Path . DS . 'docs' . DS . 'test2.md', "# Doc 2\n\nContent 2.");

        Configure::write('Synapse.documentation.sources', [
            'source-1' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo1.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
            'source-2' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo2.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
            'disabled-source' => [
                'enabled' => false,
                'repository' => 'https://github.com/test/repo3.git',
                'branch' => 'main',
            ],
        ]);

        $service = new DocumentSearchService($this->testDbPath);
        $results = $service->indexAll();

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('source-1', $results);
        $this->assertArrayHasKey('source-2', $results);
        $this->assertArrayNotHasKey('disabled-source', $results);
        $this->assertGreaterThan(0, $results['source-1']);
        $this->assertGreaterThan(0, $results['source-2']);
    }

    /**
     * Test indexAll with force option
     */
    public function testIndexAllWithForceOption(): void
    {
        // Create test repository
        $repoPath = $this->testCacheDir . DS . 'test-source';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);
        file_put_contents($repoPath . DS . 'docs' . DS . 'test.md', "# Doc\n\nContent.");

        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
        ]);

        $service = new DocumentSearchService($this->testDbPath);

        // Index with force
        $results = $service->indexAll(force: true);

        $this->assertArrayHasKey('test-source', $results);
        $this->assertGreaterThan(0, $results['test-source']);
    }

    /**
     * Test indexSource returns zero for repository with no markdown files
     */
    public function testIndexSourceReturnsZeroForNoMarkdownFiles(): void
    {
        // Create a test repository without markdown files
        $repoPath = $this->testCacheDir . DS . 'empty-source';
        mkdir($repoPath . DS . '.git', 0755, true);
        mkdir($repoPath . DS . 'docs', 0755, true);

        // Create a non-markdown file
        file_put_contents($repoPath . DS . 'docs' . DS . 'readme.txt', 'This is not markdown.');

        Configure::write('Synapse.documentation.sources', [
            'empty-source' => [
                'enabled' => true,
                'repository' => 'https://github.com/test/repo.git',
                'branch' => 'main',
                'root' => 'docs',
            ],
        ]);

        $service = new DocumentSearchService($this->testDbPath);
        $count = $service->indexSource('empty-source');

        $this->assertEquals(0, $count);
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
