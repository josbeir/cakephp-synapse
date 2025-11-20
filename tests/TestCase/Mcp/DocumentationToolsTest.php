<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Mcp;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextResourceContents;
use RuntimeException;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Documentation\SearchEngine;
use Synapse\Mcp\DocumentationTools;

/**
 * DocumentationTools Test Case
 *
 * Tests for documentation search tools and resources.
 */
class DocumentationToolsTest extends TestCase
{
    private DocumentationTools $tools;

    private DocumentSearchService $mockService;

    private SearchEngine $mockSearchEngine;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock search service
        /** @var DocumentSearchService&\PHPUnit\Framework\MockObject\MockObject $mockService */
        $mockService = $this->createMock(DocumentSearchService::class);
        $this->mockService = $mockService;

        /** @var SearchEngine&\PHPUnit\Framework\MockObject\MockObject $mockSearchEngine */
        $mockSearchEngine = $this->getMockBuilder(SearchEngine::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDocumentById', '__destruct'])
            ->getMock();
        $this->mockSearchEngine = $mockSearchEngine;

        $this->tools = new DocumentationTools($this->mockService);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        unset($this->tools);
        unset($this->mockService);
        unset($this->mockSearchEngine);

        parent::tearDown();
    }

    /**
     * Test searchDocs with basic query
     */
    public function testSearchDocsBasicQuery(): void
    {
        $expectedResults = [
            [
                'title' => 'Authentication',
                'path' => 'authentication.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Authentication component...',
                'rank' => 2.5,
            ],
            [
                'title' => 'Authorization',
                'path' => 'authorization.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Authorization component...',
                'rank' => 1.8,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'authentication',
                [
                    'limit' => 10,
                    'highlight' => true,
                ],
            )
            ->willReturn($expectedResults);

        $result = $this->tools->searchDocs('authentication');

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('options', $result);

        $this->assertEquals($expectedResults, $result['results']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals('authentication', $result['query']);
        $this->assertEquals(10, $result['options']['limit']);
        $this->assertFalse($result['options']['fuzzy']);
    }

    /**
     * Test searchDocs with custom limit
     */
    public function testSearchDocsWithCustomLimit(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 5;
                }),
            )
            ->willReturn([]);

        $result = $this->tools->searchDocs('query', limit: 5);

        $this->assertEquals(5, $result['options']['limit']);
    }

    /**
     * Test searchDocs with fuzzy search enabled
     */
    public function testSearchDocsWithFuzzySearch(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return isset($options['fuzzy']) && $options['fuzzy'] === true;
                }),
            )
            ->willReturn([]);

        $result = $this->tools->searchDocs('query', fuzzy: true);

        $this->assertTrue($result['options']['fuzzy']);
    }

    /**
     * Test searchDocs with source filter
     */
    public function testSearchDocsWithSourceFilter(): void
    {
        $sources = ['cakephp-5x', 'cakephp-4x'];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options) use ($sources): bool {
                    return isset($options['sources']) && $options['sources'] === $sources;
                }),
            )
            ->willReturn([]);

        $result = $this->tools->searchDocs('query', sources: $sources);

        $this->assertEquals($sources, $result['options']['sources']);
    }

    /**
     * Test searchDocs validates limit bounds
     */
    public function testSearchDocsValidatesLimitBounds(): void
    {
        // Test minimum limit (below 1 should become 1)
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 1;
                }),
            )
            ->willReturn([]);

        $result = $this->tools->searchDocs('query', limit: -5);
        $this->assertEquals(1, $result['options']['limit']);
    }

    /**
     * Test searchDocs validates maximum limit
     */
    public function testSearchDocsValidatesMaximumLimit(): void
    {
        // Test maximum limit (above 50 should become 50)
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 50;
                }),
            )
            ->willReturn([]);

        $result = $this->tools->searchDocs('query', limit: 100);
        $this->assertEquals(50, $result['options']['limit']);
    }

    /**
     * Test searchDocs with empty query throws exception
     */
    public function testSearchDocsWithEmptyQueryThrowsException(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $this->tools->searchDocs('');
    }

    /**
     * Test searchDocs with whitespace-only query throws exception
     */
    public function testSearchDocsWithWhitespaceQueryThrowsException(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $this->tools->searchDocs('   ');
    }

    /**
     * Test searchDocs handles service exceptions
     */
    public function testSearchDocsHandlesServiceExceptions(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to search documentation');

        $this->tools->searchDocs('query');
    }

    /**
     * Test searchDocs with empty results
     */
    public function testSearchDocsWithEmptyResults(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->tools->searchDocs('nonexistent');

        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['total']);
    }

    /**
     * Test getStats returns statistics
     */
    public function testGetStats(): void
    {
        $expectedStats = [
            'total_documents' => 150,
            'documents_by_source' => [
                'cakephp-5x' => 100,
                'cakephp-4x' => 50,
            ],
            'sources' => ['cakephp-5x', 'cakephp-4x'],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('getStatistics')
            ->willReturn($expectedStats);

        $result = $this->tools->getStats();

        $this->assertEquals($expectedStats, $result);
        $this->assertArrayHasKey('total_documents', $result);
        $this->assertArrayHasKey('documents_by_source', $result);
        $this->assertArrayHasKey('sources', $result);
    }

    /**
     * Test getStats handles service exceptions
     */
    public function testGetStatsHandlesServiceExceptions(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('getStatistics')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to get documentation statistics');

        $this->tools->getStats();
    }

    /**
     * Test searchResource with basic query
     */
    public function testSearchResourceBasicQuery(): void
    {
        $expectedResults = [
            [
                'title' => 'Caching',
                'path' => 'caching.md',
                'source' => 'cakephp-5x',
                'snippet' => 'CakePHP provides a flexible caching...',
                'rank' => 3.2,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->with(
                'caching',
                [
                    'limit' => 10,
                    'highlight' => true,
                ],
            )
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('caching');

        $this->assertArrayHasKey('contents', $result);
        $this->assertIsArray($result['contents']);
        $this->assertCount(1, $result['contents']);

        $content = $result['contents'][0];
        $this->assertInstanceOf(TextResourceContents::class, $content);
        $this->assertEquals('docs://search/caching', $content->uri);
        $this->assertEquals('text/markdown', $content->mimeType);
        $this->assertStringContainsString('# Documentation Search: caching', $content->text);
        $this->assertStringContainsString('Found 1 result(s)', $content->text);
        $this->assertStringContainsString('## 1. Caching', $content->text);
    }

    /**
     * Test searchResource with multiple results
     */
    public function testSearchResourceWithMultipleResults(): void
    {
        $expectedResults = [
            [
                'title' => 'First Result',
                'path' => 'first.md',
                'source' => 'cakephp-5x',
                'snippet' => 'First snippet...',
                'rank' => 5.0,
            ],
            [
                'title' => 'Second Result',
                'path' => 'second.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Second snippet...',
                'rank' => 4.5,
            ],
            [
                'title' => 'Third Result',
                'path' => 'third.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Third snippet...',
                'rank' => 4.0,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('test query');
        $content = $result['contents'][0];

        $this->assertStringContainsString('Found 3 result(s)', $content->text);
        $this->assertStringContainsString('## 1. First Result', $content->text);
        $this->assertStringContainsString('## 2. Second Result', $content->text);
        $this->assertStringContainsString('## 3. Third Result', $content->text);
        $this->assertStringContainsString('**Relevance:** 5.00', $content->text);
        $this->assertStringContainsString('**Relevance:** 4.50', $content->text);
        $this->assertStringContainsString('**Relevance:** 4.00', $content->text);
    }

    /**
     * Test searchResource with empty results
     */
    public function testSearchResourceWithEmptyResults(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->tools->searchResource('nonexistent');
        $content = $result['contents'][0];

        $this->assertStringContainsString('# Documentation Search: nonexistent', $content->text);
        $this->assertStringContainsString('No results found', $content->text);
    }

    /**
     * Test searchResource with empty query throws exception
     */
    public function testSearchResourceWithEmptyQueryThrowsException(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $this->tools->searchResource('');
    }

    /**
     * Test searchResource with whitespace-only query throws exception
     */
    public function testSearchResourceWithWhitespaceQueryThrowsException(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $this->tools->searchResource('   ');
    }

    /**
     * Test searchResource handles service exceptions
     */
    public function testSearchResourceHandlesServiceExceptions(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to read documentation resource');

        $this->tools->searchResource('query');
    }

    /**
     * Test searchResource formats URI correctly
     */
    public function testSearchResourceFormatsUriCorrectly(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->tools->searchResource('test query with spaces');
        $content = $result['contents'][0];

        $this->assertEquals('docs://search/test+query+with+spaces', $content->uri);
    }

    /**
     * Test searchResource includes all result fields
     */
    public function testSearchResourceIncludesAllResultFields(): void
    {
        $expectedResults = [
            [
                'title' => 'Complete Result',
                'path' => 'docs/complete.md',
                'source' => 'cakephp-5x',
                'snippet' => 'This is a complete snippet with all fields.',
                'rank' => 7.25,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('complete');
        $content = $result['contents'][0];

        $this->assertStringContainsString('## 1. Complete Result', $content->text);
        $this->assertStringContainsString('**Source:** cakephp-5x', $content->text);
        $this->assertStringContainsString('**Path:** `docs/complete.md`', $content->text);
        $this->assertStringContainsString('**Relevance:** 7.25', $content->text);
        $this->assertStringContainsString('**Snippet:**', $content->text);
        $this->assertStringContainsString('This is a complete snippet with all fields.', $content->text);
    }

    /**
     * Test searchResource handles missing optional fields
     */
    public function testSearchResourceHandlesMissingOptionalFields(): void
    {
        $expectedResults = [
            [
                'title' => 'Minimal Result',
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('minimal');
        $content = $result['contents'][0];

        $this->assertStringContainsString('## 1. Minimal Result', $content->text);
        $this->assertStringContainsString('**Source:**', $content->text);
        $this->assertStringContainsString('**Relevance:** 0.00', $content->text);
    }

    /**
     * Test searchResource handles untitled documents
     */
    public function testSearchResourceHandlesUntitledDocuments(): void
    {
        $expectedResults = [
            [
                'path' => 'untitled.md',
                'source' => 'cakephp-5x',
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('query');
        $content = $result['contents'][0];

        $this->assertStringContainsString('## 1. Untitled', $content->text);
    }

    /**
     * Test searchResource formats snippets with blockquotes
     */
    public function testSearchResourceFormatsSnippetsWithBlockquotes(): void
    {
        $expectedResults = [
            [
                'title' => 'Result',
                'path' => 'result.md',
                'source' => 'cakephp-5x',
                'snippet' => "Line 1\nLine 2\nLine 3",
                'rank' => 1.0,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('query');
        $content = $result['contents'][0];

        $this->assertStringContainsString('> Line 1', $content->text);
        $this->assertStringContainsString('> Line 2', $content->text);
        $this->assertStringContainsString('> Line 3', $content->text);
    }

    /**
     * Test searchResource includes separators between results
     */
    public function testSearchResourceIncludesSeparatorsBetweenResults(): void
    {
        $expectedResults = [
            [
                'title' => 'First',
                'path' => 'first.md',
                'source' => 'cakephp-5x',
                'snippet' => 'First snippet',
                'rank' => 1.0,
            ],
            [
                'title' => 'Second',
                'path' => 'second.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Second snippet',
                'rank' => 0.9,
            ],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->tools->searchResource('query');
        $content = $result['contents'][0];

        // Should have separator between results
        $this->assertStringContainsString('---', $content->text);
        $this->assertGreaterThan(1, substr_count($content->text, '---'));
    }

    /**
     * Test constructor with no service creates default service
     */
    public function testConstructorWithNoServiceCreatesDefaultService(): void
    {
        $tools = new DocumentationTools();

        // Should not throw exception and should be usable
        $this->assertInstanceOf(DocumentationTools::class, $tools);
    }

    /**
     * Test constructor with custom service uses provided service
     */
    public function testConstructorWithCustomServiceUsesProvidedService(): void
    {
        /** @var DocumentSearchService&\PHPUnit\Framework\MockObject\MockObject $customService */
        $customService = $this->createMock(DocumentSearchService::class);
        $tools = new DocumentationTools($customService);

        $customService->expects($this->once())
            ->method('getStatistics')
            ->willReturn([
                'total_documents' => 42,
                'documents_by_source' => [],
                'sources' => [],
            ]);

        $result = $tools->getStats();
        $this->assertEquals(42, $result['total_documents']);
    }

    /**
     * Test getDocument retrieves full document content
     */
    public function testGetDocumentRetrievesFullContent(): void
    {
        $documentData = [
            'id' => 'cakephp-5x::docs/getting-started.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/getting-started.md',
            'title' => 'Getting Started',
            'content' => "# Getting Started\n\nThis is the full documentation content.",
            'metadata' => ['version' => '5.x'],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/getting-started.md')
            ->willReturn($documentData);

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($this->mockSearchEngine);

        $result = $this->tools->getDocument('cakephp-5x::docs/getting-started.md');

        $this->assertEquals($documentData['content'], $result['content']);
        $this->assertEquals('Getting Started', $result['title']);
        $this->assertEquals('cakephp-5x', $result['source']);
        $this->assertEquals('docs/getting-started.md', $result['path']);
        $this->assertEquals('cakephp-5x::docs/getting-started.md', $result['id']);
    }

    /**
     * Test getDocument returns title from database
     */
    public function testGetDocumentReturnsTitleFromDatabase(): void
    {
        $documentData = [
            'id' => 'cakephp-5x::docs/auth.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/auth.md',
            'title' => 'Authentication & Authorization',
            'content' => "# Authentication & Authorization\n\nLearn about security.",
            'metadata' => [],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->method('getDocumentById')->willReturn($documentData);

        /** @phpstan-ignore-next-line */
        $this->mockService->method('getSearchEngine')->willReturn($this->mockSearchEngine);

        $result = $this->tools->getDocument('cakephp-5x::docs/auth.md');

        $this->assertEquals('Authentication & Authorization', $result['title']);
    }

    /**
     * Test getDocument returns metadata
     */
    public function testGetDocumentReturnsMetadata(): void
    {
        $documentData = [
            'id' => 'cakephp-5x::docs/database-basics.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/database-basics.md',
            'title' => 'Database Basics',
            'content' => 'Some content without a heading.',
            'metadata' => ['author' => 'CakePHP Team', 'version' => '5.x'],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->method('getDocumentById')->willReturn($documentData);

        /** @phpstan-ignore-next-line */
        $this->mockService->method('getSearchEngine')->willReturn($this->mockSearchEngine);

        $result = $this->tools->getDocument('cakephp-5x::docs/database-basics.md');

        $this->assertEquals('Database Basics', $result['title']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('CakePHP Team', $result['metadata']['author']);
    }

    /**
     * Test getDocument throws exception for empty document ID
     */
    public function testGetDocumentThrowsExceptionForEmptyDocId(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document ID cannot be empty');

        $this->tools->getDocument('');
    }

    /**
     * Test getDocument throws exception when document not found
     */
    public function testGetDocumentThrowsExceptionWhenNotFound(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->method('getDocumentById')->willReturn(null);

        /** @phpstan-ignore-next-line */
        $this->mockService->method('getSearchEngine')->willReturn($this->mockSearchEngine);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document not found');

        $this->tools->getDocument('cakephp-5x::docs/nonexistent.md');
    }

    /**
     * Test getDocument handles search engine errors
     */
    public function testGetDocumentHandlesSearchEngineErrors(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('getSearchEngine')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to get document');

        $this->tools->getDocument('cakephp-5x::docs/test.md');
    }

    /**
     * Test contentResource retrieves full content
     */
    public function testContentResourceRetrievesFullContent(): void
    {
        $documentData = [
            'id' => 'cakephp-5x::docs/controllers.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/controllers.md',
            'title' => 'Controllers',
            'content' => "# Controllers\n\nLearn about CakePHP controllers.",
            'metadata' => [],
        ];

        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/controllers.md')
            ->willReturn($documentData);

        /** @phpstan-ignore-next-line */
        $this->mockService->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($this->mockSearchEngine);

        $result = $this->tools->contentResource('cakephp-5x::docs/controllers.md');

        $this->assertArrayHasKey('contents', $result);
        $this->assertCount(1, $result['contents']);
        $this->assertInstanceOf(TextResourceContents::class, $result['contents'][0]);
        $this->assertEquals('docs://content/cakephp-5x::docs/controllers.md', $result['contents'][0]->uri);
        $this->assertEquals('text/markdown', $result['contents'][0]->mimeType);
        $this->assertEquals($documentData['content'], $result['contents'][0]->text);
    }

    /**
     * Test contentResource throws exception for empty document ID
     */
    public function testContentResourceThrowsExceptionForEmptyDocId(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document ID cannot be empty');

        $this->tools->contentResource('');
    }

    /**
     * Test contentResource throws exception for nonexistent document
     */
    public function testContentResourceThrowsExceptionForNonexistentDocument(): void
    {
        /** @phpstan-ignore-next-line */
        $this->mockSearchEngine->method('getDocumentById')->willReturn(null);

        /** @phpstan-ignore-next-line */
        $this->mockService->method('getSearchEngine')->willReturn($this->mockSearchEngine);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document not found');

        $this->tools->contentResource('cakephp-5x::docs/missing.md');
    }
}
