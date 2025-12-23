<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Documentation\SearchEngine;
use Synapse\Tools\DocumentationTools;

/**
 * DocumentationTools Test Case
 *
 * Tests for documentation search tools.
 */
class DocumentationToolsTest extends TestCase
{
    private ?DocumentationTools $tools = null;

    /**
     * @var \Synapse\Documentation\DocumentSearchService&\PHPUnit\Framework\MockObject\MockObject
     */
    private ?MockObject $mockService = null;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create mock service and tools for tests that need them
     */
    private function createMockServiceAndTools(): void
    {
        $this->mockService = $this->createMock(DocumentSearchService::class);
        $this->tools = new DocumentationTools($this->mockService);
    }

    /**
     * Get the mock service (with assertion for PHPStan)
     *
     * @return \Synapse\Documentation\DocumentSearchService&\PHPUnit\Framework\MockObject\MockObject
     */
    private function getMockService(): MockObject
    {
        $this->assertNotNull($this->mockService);

        return $this->mockService;
    }

    /**
     * Get the tools instance (with assertion for PHPStan)
     */
    private function getTools(): DocumentationTools
    {
        $this->assertNotNull($this->tools);

        return $this->tools;
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        $this->tools = null;
        $this->mockService = null;

        parent::tearDown();
    }

    /**
     * Test searchDocs with basic query
     */
    public function testSearchDocsBasicQuery(): void
    {
        $this->createMockServiceAndTools();

        $expectedResults = [
            [
                'title' => 'Authentication',
                'path' => 'authentication.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Authentication component...',
                'score' => 2.5,
            ],
            [
                'title' => 'Authorization',
                'path' => 'authorization.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Authorization component...',
                'score' => 1.8,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'authentication',
                [
                    'limit' => 10,
                    'highlight' => true,
                ],
            )
            ->willReturn($expectedResults);

        $result = $this->getTools()->searchDocs('authentication');

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
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 5;
                }),
            )
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('query', limit: 5);

        $this->assertEquals(5, $result['options']['limit']);
    }

    /**
     * Test searchDocs with fuzzy search enabled
     */
    public function testSearchDocsWithFuzzySearch(): void
    {
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return isset($options['fuzzy']) && $options['fuzzy'] === true;
                }),
            )
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('query', fuzzy: true);

        $this->assertTrue($result['options']['fuzzy']);
    }

    /**
     * Test searchDocs with source filter
     */
    public function testSearchDocsWithSourceFilter(): void
    {
        $this->createMockServiceAndTools();

        $sourcesString = 'cakephp-5x,cakephp-4x';
        $sourcesArray = ['cakephp-5x', 'cakephp-4x'];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options) use ($sourcesArray): bool {
                    return isset($options['sources']) && $options['sources'] === $sourcesArray;
                }),
            )
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('query', sources: $sourcesString);

        $this->assertEquals($sourcesString, $result['options']['sources']);
    }

    /**
     * Test searchDocs validates limit bounds
     */
    public function testSearchDocsValidatesLimitBounds(): void
    {
        $this->createMockServiceAndTools();

        // Test minimum limit (below 1 should become 1)
        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 1;
                }),
            )
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('query', limit: -5);
        $this->assertEquals(1, $result['options']['limit']);
    }

    /**
     * Test searchDocs validates maximum limit
     */
    public function testSearchDocsValidatesMaximumLimit(): void
    {
        $this->createMockServiceAndTools();

        // Test maximum limit (above 50 should become 50)
        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'query',
                $this->callback(function (array $options): bool {
                    return $options['limit'] === 50;
                }),
            )
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('query', limit: 100);
        $this->assertEquals(50, $result['options']['limit']);
    }

    /**
     * Test searchDocs with empty query throws exception
     */
    public function testSearchDocsWithEmptyQueryThrowsException(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $tools = new DocumentationTools($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $tools->searchDocs('');
    }

    /**
     * Test searchDocs with whitespace-only query throws exception
     */
    public function testSearchDocsWithWhitespaceQueryThrowsException(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $tools = new DocumentationTools($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $tools->searchDocs('   ');
    }

    /**
     * Test searchDocs handles service exceptions
     */
    public function testSearchDocsHandlesServiceExceptions(): void
    {
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to search documentation');

        $this->getTools()->searchDocs('query');
    }

    /**
     * Test searchDocs with empty results
     */
    public function testSearchDocsWithEmptyResults(): void
    {
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->getTools()->searchDocs('nonexistent');

        $this->assertEmpty($result['results']);
        $this->assertEquals(0, $result['total']);
    }

    /**
     * Test getStats returns statistics
     */
    public function testGetStats(): void
    {
        $this->createMockServiceAndTools();

        $expectedStats = [
            'total_documents' => 150,
            'documents_by_source' => [
                'cakephp-5x' => 100,
                'cakephp-4x' => 50,
            ],
            'sources' => ['cakephp-5x', 'cakephp-4x'],
        ];

        $this->getMockService()->expects($this->once())
            ->method('getStatistics')
            ->willReturn($expectedStats);

        $result = $this->getTools()->getStats();

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
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('getStatistics')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to get documentation statistics');

        $this->getTools()->getStats();
    }

    /**
     * Test constructor with no service creates default service
     */
    public function testConstructorWithNoServiceCreatesDefaultService(): void
    {
        // No mocks needed for this test
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
        $customService->expects($this->once())
            ->method('getStatistics')
            ->willReturn([
                'total_documents' => 42,
                'documents_by_source' => [],
                'sources' => [],
            ]);

        $tools = new DocumentationTools($customService);

        $result = $tools->getStats();
        $this->assertEquals(42, $result['total_documents']);
    }

    /**
     * Test getDocument retrieves full document content
     */
    public function testGetDocumentRetrievesFullContent(): void
    {
        $this->createMockServiceAndTools();

        $documentData = [
            'id' => 'cakephp-5x::docs/getting-started.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/getting-started.md',
            'title' => 'Getting Started',
            'content' => "# Getting Started\n\nThis is the full documentation content.",
            'metadata' => ['version' => '5.x'],
        ];

        $mockSearchEngine = $this->createMock(SearchEngine::class);
        $mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/getting-started.md')
            ->willReturn($documentData);

        $this->getMockService()->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($mockSearchEngine);

        $result = $this->getTools()->getDocument('cakephp-5x::docs/getting-started.md');

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

        // Use stubs instead of mocks - no expectations needed
        $stubSearchEngine = $this->createStub(SearchEngine::class);
        $stubSearchEngine->method('getDocumentById')->willReturn($documentData);

        $stubService = $this->createStub(DocumentSearchService::class);
        $stubService->method('getSearchEngine')->willReturn($stubSearchEngine);
        $tools = new DocumentationTools($stubService);

        $result = $tools->getDocument('cakephp-5x::docs/auth.md');

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

        // Use stubs instead of mocks - no expectations needed
        $stubSearchEngine = $this->createStub(SearchEngine::class);
        $stubSearchEngine->method('getDocumentById')->willReturn($documentData);

        $stubService = $this->createStub(DocumentSearchService::class);
        $stubService->method('getSearchEngine')->willReturn($stubSearchEngine);
        $tools = new DocumentationTools($stubService);

        $result = $tools->getDocument('cakephp-5x::docs/database-basics.md');

        $this->assertEquals('Database Basics', $result['title']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('CakePHP Team', $result['metadata']['author']);
    }

    /**
     * Test getDocument throws exception for empty document ID
     */
    public function testGetDocumentThrowsExceptionForEmptyDocId(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $tools = new DocumentationTools($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document ID cannot be empty');

        $tools->getDocument('');
    }

    /**
     * Test getDocument throws exception when document not found
     */
    public function testGetDocumentThrowsExceptionWhenNotFound(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubSearchEngine = $this->createStub(SearchEngine::class);
        $stubSearchEngine->method('getDocumentById')->willReturn(null);

        $stubService = $this->createStub(DocumentSearchService::class);
        $stubService->method('getSearchEngine')->willReturn($stubSearchEngine);
        $tools = new DocumentationTools($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document not found');

        $tools->getDocument('cakephp-5x::docs/nonexistent.md');
    }

    /**
     * Test getDocument handles search engine errors
     */
    public function testGetDocumentHandlesSearchEngineErrors(): void
    {
        $this->createMockServiceAndTools();

        $this->getMockService()->expects($this->once())
            ->method('getSearchEngine')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to get document');

        $this->getTools()->getDocument('cakephp-5x::docs/test.md');
    }

    /**
     * Test getDocument returns original markdown with formatting preserved
     */
    public function testGetDocumentReturnsOriginalMarkdownWithFormatting(): void
    {
        $this->createMockServiceAndTools();

        $documentData = [
            'id' => 'cakephp-5x::docs/example.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/example.md',
            'title' => 'Example Document',
            'content' => "# Example Document\n\nThis is **bold** and *italic* text.\n\n```php\necho \"Code block\";\n```\n\n- List item 1\n- List item 2\n\n[Link text](https://example.com)",
            'metadata' => [],
        ];

        $mockSearchEngine = $this->createMock(SearchEngine::class);
        $mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/example.md')
            ->willReturn($documentData);

        $this->getMockService()->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($mockSearchEngine);

        $result = $this->getTools()->getDocument('cakephp-5x::docs/example.md');

        // Verify original markdown formatting is preserved
        $this->assertStringContainsString('**bold**', $result['content']);
        $this->assertStringContainsString('*italic*', $result['content']);
        $this->assertStringContainsString('```php', $result['content']);
        $this->assertStringContainsString('- List item 1', $result['content']);
        $this->assertStringContainsString('[Link text](https://example.com)', $result['content']);
    }
}
