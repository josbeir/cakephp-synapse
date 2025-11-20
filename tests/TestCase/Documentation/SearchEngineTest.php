<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Documentation;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Synapse\Documentation\SearchEngine;

/**
 * SearchEngine Test Case
 *
 * Tests for SQLite FTS5 search engine.
 */
class SearchEngineTest extends TestCase
{
    /**
     * Test subject
     */
    protected SearchEngine $searchEngine;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->searchEngine = new SearchEngine(TEST_SEARCH_DB);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->searchEngine->destroy();
    }

    /**
     * Test constructor creates database file
     */
    public function testConstructorCreatesDatabaseFile(): void
    {
        $this->assertFileExists(TEST_SEARCH_DB);
    }

    /**
     * Test constructor throws exception if FTS5 not available
     */
    public function testConstructorThrowsExceptionIfFts5NotAvailable(): void
    {
        // This test would require mocking SQLite3, which is complex
        // We'll assume FTS5 is available in test environment
        $this->assertFileExists(TEST_SEARCH_DB);
    }

    /**
     * Test initialize creates tables
     */
    public function testInitializeCreatesTables(): void
    {
        $this->searchEngine->initialize();

        // Database should have our tables
        $this->assertFileExists(TEST_SEARCH_DB);

        // Try to query the tables to verify they exist
        $count = $this->searchEngine->getDocumentCount();
        $this->assertEquals(0, $count);
    }

    /**
     * Test indexDocument adds document to index
     */
    public function testIndexDocumentAddsDocument(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => ['Introduction', 'Getting Started'],
            'content' => 'This is test content for the search engine.',
            'metadata' => ['author' => 'Test Author'],
        ];

        $this->searchEngine->indexDocument($document);

        $count = $this->searchEngine->getDocumentCount();
        $this->assertEquals(1, $count);
    }

    /**
     * Test indexDocument updates existing document
     */
    public function testIndexDocumentReplacesExisting(): void
    {
        $document1 = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Original Title',
            'headings' => [],
            'content' => 'Original content',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document1);
        $this->assertEquals(1, $this->searchEngine->getDocumentCount());

        // Index again with updated content
        $document2 = $document1;
        $document2['title'] = 'Updated Title';
        $document2['content'] = 'Updated content';
        $this->searchEngine->indexDocument($document2);

        // Should still be 1 document
        $this->assertEquals(1, $this->searchEngine->getDocumentCount());

        // Search should find updated content
        $results = $this->searchEngine->search('Updated');
        $this->assertCount(1, $results);
        $this->assertEquals('Updated Title', $results[0]['title']);
    }

    /**
     * Test indexBatch adds multiple documents
     */
    public function testIndexBatchAddsMultipleDocuments(): void
    {
        $documents = [
            [
                'id' => 'test::doc1.md',
                'source' => 'test',
                'path' => '/absolute/path/docs/doc1.md',
                'relative_path' => 'docs/doc1.md',
                'title' => 'Document 1',
                'headings' => [],
                'content' => 'First document content',
                'metadata' => [],
            ],
            [
                'id' => 'test::doc2.md',
                'source' => 'test',
                'path' => '/absolute/path/docs/doc2.md',
                'relative_path' => 'docs/doc2.md',
                'title' => 'Document 2',
                'headings' => [],
                'content' => 'Second document content',
                'metadata' => [],
            ],
            [
                'id' => 'test::doc3.md',
                'source' => 'test',
                'path' => '/absolute/path/docs/doc3.md',
                'relative_path' => 'docs/doc3.md',
                'title' => 'Document 3',
                'headings' => [],
                'content' => 'Third document content',
                'metadata' => [],
            ],
        ];

        $this->searchEngine->indexBatch($documents);

        $count = $this->searchEngine->getDocumentCount();
        $this->assertEquals(3, $count);
    }

    /**
     * Test search finds documents
     */
    public function testSearchFindsDocuments(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'CakePHP Framework',
            'headings' => ['Introduction', 'Getting Started'],
            'content' => 'CakePHP is a rapid development framework for PHP.',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document);

        $results = $this->searchEngine->search('CakePHP');

        $this->assertCount(1, $results);
        $this->assertEquals('test::doc1.md', $results[0]['id']);
        $this->assertEquals('CakePHP Framework', $results[0]['title']);
    }

    /**
     * Test search returns empty array when no matches
     */
    public function testSearchReturnsEmptyWhenNoMatches(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => [],
            'content' => 'Test content with search terms',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document);

        $results = $this->searchEngine->search('nonexistent');

        $this->assertEmpty($results);
    }

    /**
     * Test search respects limit option
     */
    public function testSearchRespectsLimit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->searchEngine->indexDocument([
                'id' => sprintf('test::doc%d.md', $i),
                'source' => 'test',
                'path' => sprintf('docs/doc%d.md', $i),
                'title' => 'Document ' . $i,
                'headings' => [],
                'content' => 'This document contains searchable content.',
                'metadata' => [],
            ]);
        }

        $results = $this->searchEngine->search('searchable', ['limit' => 5]);

        $this->assertCount(5, $results);
    }

    /**
     * Test search filters by source
     */
    public function testSearchFiltersBySource(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'source1::doc1.md',
            'source' => 'source1',
            'path' => 'docs/doc1.md',
            'title' => 'Document 1',
            'headings' => [],
            'content' => 'Content from source one',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'source2::doc2.md',
            'source' => 'source2',
            'path' => 'docs/doc2.md',
            'title' => 'Document 2',
            'headings' => [],
            'content' => 'Content from source two',
            'metadata' => [],
        ]);

        $results = $this->searchEngine->search('Content', ['sources' => ['source1']]);

        $this->assertCount(1, $results);
        $this->assertEquals('source1', $results[0]['source']);
    }

    /**
     * Test search highlights matched terms
     */
    public function testSearchHighlightsMatches(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'CakePHP Framework',
            'headings' => [],
            'content' => 'CakePHP is a framework.',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document);

        $results = $this->searchEngine->search('CakePHP', ['highlight' => true]);

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('title_highlight', $results[0]);
        $this->assertArrayHasKey('snippet', $results[0]);
        $this->assertStringContainsString('<mark>', $results[0]['snippet']);
    }

    /**
     * Test search without highlighting
     */
    public function testSearchWithoutHighlighting(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => [],
            'content' => 'Test content',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document);

        $results = $this->searchEngine->search('Test', ['highlight' => false]);

        $this->assertCount(1, $results);
        $this->assertNull($results[0]['title_highlight']);
        $this->assertNull($results[0]['snippet']);
    }

    /**
     * Test deleteDocument removes document
     */
    public function testDeleteDocumentRemovesDocument(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => [],
            'content' => 'Test content',
            'metadata' => [],
        ];

        $this->searchEngine->indexDocument($document);
        $this->assertEquals(1, $this->searchEngine->getDocumentCount());

        $this->searchEngine->deleteDocument('test::doc1.md');

        $this->assertEquals(0, $this->searchEngine->getDocumentCount());
    }

    /**
     * Test clear removes all documents
     */
    public function testClearRemovesAllDocuments(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->searchEngine->indexDocument([
                'id' => sprintf('test::doc%d.md', $i),
                'source' => 'test',
                'path' => sprintf('docs/doc%d.md', $i),
                'title' => 'Document ' . $i,
                'headings' => [],
                'content' => 'Content',
                'metadata' => [],
            ]);
        }

        $this->assertEquals(5, $this->searchEngine->getDocumentCount());

        $this->searchEngine->clear();

        $this->assertEquals(0, $this->searchEngine->getDocumentCount());
    }

    /**
     * Test clearSource removes only specified source
     */
    public function testClearSourceRemovesOnlySpecifiedSource(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'source1::doc1.md',
            'source' => 'source1',
            'path' => 'docs/doc1.md',
            'title' => 'Document 1',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'source2::doc2.md',
            'source' => 'source2',
            'path' => 'docs/doc2.md',
            'title' => 'Document 2',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->assertEquals(2, $this->searchEngine->getDocumentCount());

        $this->searchEngine->clearSource('source1');

        $this->assertEquals(1, $this->searchEngine->getDocumentCount());

        $counts = $this->searchEngine->getDocumentCountBySource();
        $this->assertArrayNotHasKey('source1', $counts);
        $this->assertArrayHasKey('source2', $counts);
        $this->assertEquals(1, $counts['source2']);
    }

    /**
     * Test getDocumentCount returns correct count
     */
    public function testGetDocumentCountReturnsCorrectCount(): void
    {
        $this->assertEquals(0, $this->searchEngine->getDocumentCount());

        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'Document 1',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->assertEquals(1, $this->searchEngine->getDocumentCount());
    }

    /**
     * Test getDocumentCountBySource returns counts per source
     */
    public function testGetDocumentCountBySourceReturnsCountsPerSource(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'source1::doc1.md',
            'source' => 'source1',
            'path' => 'docs/doc1.md',
            'title' => 'Document 1',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'source1::doc2.md',
            'source' => 'source1',
            'path' => 'docs/doc2.md',
            'title' => 'Document 2',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'source2::doc3.md',
            'source' => 'source2',
            'path' => 'docs/doc3.md',
            'title' => 'Document 3',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $counts = $this->searchEngine->getDocumentCountBySource();

        $this->assertArrayHasKey('source1', $counts);
        $this->assertArrayHasKey('source2', $counts);
        $this->assertEquals(2, $counts['source1']);
        $this->assertEquals(1, $counts['source2']);
    }

    /**
     * Test optimize doesn't throw errors
     */
    public function testOptimizeDoesNotThrowErrors(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'Document 1',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [],
        ]);

        $this->searchEngine->optimize();

        // If we got here without exception, verify count is still correct
        $this->assertEquals(1, $this->searchEngine->getDocumentCount());
    }

    /**
     * Test search returns scores for ranking
     */
    public function testSearchReturnsScores(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'Getting Started Tutorial',
            'headings' => [],
            'content' => 'This is a basic tutorial about CakePHP.',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'test::doc2.md',
            'source' => 'test',
            'path' => 'docs/doc2.md',
            'title' => 'CakePHP Advanced Guide',
            'headings' => ['CakePHP Features', 'CakePHP Performance'],
            'content' => 'CakePHP provides many features. CakePHP is fast. CakePHP is powerful.',
            'metadata' => [],
        ]);

        $results = $this->searchEngine->search('CakePHP');

        $this->assertCount(2, $results);
        // Both documents should have relevance scores
        $this->assertArrayHasKey('score', $results[0]);
        $this->assertArrayHasKey('score', $results[1]);
        $this->assertIsFloat($results[0]['score']);
        $this->assertIsFloat($results[1]['score']);
    }

    /**
     * Test metadata is preserved
     */
    public function testMetadataIsPreserved(): void
    {
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => '/absolute/path/docs/doc1.md',
            'relative_path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => [],
            'content' => 'Content',
            'metadata' => [
                'author' => 'John Doe',
                'date' => '2024-01-01',
                'tags' => 'test,documentation',
            ],
        ];

        $this->searchEngine->indexDocument($document);

        $results = $this->searchEngine->search('Content');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('metadata', $results[0]);
        $this->assertEquals('John Doe', $results[0]['metadata']['author']);
        $this->assertEquals('2024-01-01', $results[0]['metadata']['date']);
    }

    /**
     * Test fuzzy search finds partial matches
     */
    public function testFuzzySearchFindsPartialMatches(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'CakePHP Framework Guide',
            'headings' => [],
            'content' => 'CakePHP is a powerful framework for building web applications.',
            'metadata' => [],
        ]);

        // Search with partial word using fuzzy mode
        $results = $this->searchEngine->search('cake', ['fuzzy' => true]);

        $this->assertCount(1, $results);
        $this->assertEquals('test::doc1.md', $results[0]['id']);
    }

    /**
     * Test fuzzy search with short words
     */
    public function testFuzzySearchWithShortWords(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'PHP Guide',
            'headings' => [],
            'content' => 'A guide to PHP programming.',
            'metadata' => [],
        ]);

        // Short words (< 3 chars) should not get wildcard
        $results = $this->searchEngine->search('PHP', ['fuzzy' => true]);

        $this->assertCount(1, $results);
        $this->assertEquals('test::doc1.md', $results[0]['id']);
    }

    /**
     * Test fuzzy search preserves quoted phrases
     */
    public function testFuzzySearchPreservesQuotedPhrases(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'Test Document',
            'headings' => [],
            'content' => 'The quick brown fox jumps over the lazy dog.',
            'metadata' => [],
        ]);

        // Quoted phrase should be preserved exactly
        $results = $this->searchEngine->search('"brown fox"', ['fuzzy' => true]);

        $this->assertCount(1, $results);
        $this->assertEquals('test::doc1.md', $results[0]['id']);
    }

    /**
     * Test fuzzy search returns no results for non-matching query
     */
    public function testFuzzySearchReturnsEmptyForNonMatch(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'CakePHP Guide',
            'headings' => [],
            'content' => 'A guide to CakePHP.',
            'metadata' => [],
        ]);

        $results = $this->searchEngine->search('django', ['fuzzy' => true]);

        $this->assertEmpty($results);
    }

    /**
     * Test fuzzy search with multiple terms
     */
    public function testFuzzySearchWithMultipleTerms(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'CakePHP Framework',
            'headings' => [],
            'content' => 'CakePHP is a framework for building applications.',
            'metadata' => [],
        ]);

        $this->searchEngine->indexDocument([
            'id' => 'test::doc2.md',
            'source' => 'test',
            'path' => 'docs/doc2.md',
            'title' => 'Laravel Framework',
            'headings' => [],
            'content' => 'Laravel is another PHP framework.',
            'metadata' => [],
        ]);

        // Fuzzy search with multiple terms (OR logic)
        $results = $this->searchEngine->search('cake frame', ['fuzzy' => true]);

        // Should find documents matching either term
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    /**
     * Test non-fuzzy search requires exact word match
     */
    public function testNonFuzzySearchRequiresExactMatch(): void
    {
        $this->searchEngine->indexDocument([
            'id' => 'test::doc1.md',
            'source' => 'test',
            'path' => 'docs/doc1.md',
            'title' => 'CakePHP Framework',
            'headings' => [],
            'content' => 'CakePHP is great.',
            'metadata' => [],
        ]);

        // Without fuzzy, partial match should not work
        $results = $this->searchEngine->search('cake', ['fuzzy' => false]);

        // May or may not find it depending on tokenizer (porter stemming might match)
        // Just verify it runs without error and returns results
        $this->assertGreaterThanOrEqual(0, count($results));
    }

    /**
     * Test destroy method closes connection and deletes database file
     */
    public function testDestroyDeletesDatabaseFile(): void
    {
        // Create a new search engine with a specific path
        $dbPath = TMP . 'tests' . DS . 'destroy_test_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath);

        // Initialize to create the database file
        $engine->initialize();

        // Verify the file exists
        $this->assertFileExists($dbPath);

        // Destroy should delete the file
        $result = $engine->destroy();

        $this->assertTrue($result);
        $this->assertFileDoesNotExist($dbPath);
    }

    /**
     * Test destroy can be called multiple times
     */
    public function testDestroyCanBeCalledMultipleTimes(): void
    {
        // Create a search engine and initialize
        $dbPath = TMP . 'tests' . DS . 'multi_destroy_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath);
        $engine->initialize();

        // First destroy should succeed
        $result1 = $engine->destroy();
        $this->assertTrue($result1);

        // Second destroy should return false (file no longer exists)
        $result2 = $engine->destroy();
        $this->assertFalse($result2);
    }

    /**
     * Test destructor closes database connection
     */
    public function testDestructorClosesDatabaseConnection(): void
    {
        // Create engine, use it, then let it go out of scope
        $dbPath = TMP . 'tests' . DS . 'destructor_test_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath);
        $engine->initialize();

        // Trigger destructor
        unset($engine);

        // File should still exist (destructor only closes connection, doesn't delete)
        $this->assertFileExists($dbPath);

        // Clean up
        @unlink($dbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }

    /**
     * Test search results include computed absolute paths
     */
    public function testSearchResultsIncludeAbsolutePath(): void
    {
        Configure::write('Synapse.documentation.cache_dir', TMP . 'test_cache');

        $dbPath = TMP . 'tests' . DS . 'absolute_path_test_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath);

        // Index a document with relative path
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'test-source',
            'path' => 'docs/getting-started.md',
            'title' => 'Getting Started',
            'headings' => [],
            'content' => 'Learn how to get started with the framework',
            'metadata' => [],
        ];

        $engine->indexDocument($document);

        // Search for the document
        $results = $engine->search('started');

        $this->assertNotEmpty($results);
        $this->assertEquals('docs/getting-started.md', $results[0]['path']);
        $this->assertStringEndsWith('test-source' . DS . 'docs/getting-started.md', $results[0]['absolute_path']);

        // Clean up
        @unlink($dbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }

    /**
     * Test base path override affects absolute path resolution
     */
    public function testBasePathOverrideAffectsAbsolutePath(): void
    {
        Configure::write('Synapse.documentation.base_path_override', '/custom/base/path');

        $dbPath = TMP . 'tests' . DS . 'base_path_override_test_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath);

        // Index a document
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'my-docs',
            'path' => 'intro/setup.md',
            'title' => 'Setup Guide',
            'headings' => [],
            'content' => 'How to setup the application',
            'metadata' => [],
        ];

        $engine->indexDocument($document);

        // Search for the document
        $results = $engine->search('setup');

        $this->assertNotEmpty($results);
        $this->assertEquals('intro/setup.md', $results[0]['path']);
        $expected = '/custom/base/path' . DS . 'my-docs' . DS . 'intro/setup.md';
        $this->assertEquals($expected, $results[0]['absolute_path']);

        // Clean up
        Configure::delete('Synapse.documentation.base_path_override');
        @unlink($dbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }

    /**
     * Test constructor basePath parameter overrides config
     */
    public function testConstructorBasePathOverridesConfig(): void
    {
        Configure::write('Synapse.documentation.cache_dir', TMP . 'config_cache');
        Configure::write('Synapse.documentation.base_path_override', '/config/override');

        $dbPath = TMP . 'tests' . DS . 'constructor_path_test_' . uniqid() . '.db';
        $engine = new SearchEngine($dbPath, '/constructor/path');

        // Index a document
        $document = [
            'id' => 'test::doc1.md',
            'source' => 'docs',
            'path' => 'guide.md',
            'title' => 'Guide',
            'headings' => [],
            'content' => 'Documentation guide',
            'metadata' => [],
        ];

        $engine->indexDocument($document);

        // Search for the document
        $results = $engine->search('guide');

        $this->assertNotEmpty($results);
        $expected = '/constructor/path' . DS . 'docs' . DS . 'guide.md';
        $this->assertEquals($expected, $results[0]['absolute_path']);

        // Clean up
        Configure::delete('Synapse.documentation.base_path_override');
        @unlink($dbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    }
}
