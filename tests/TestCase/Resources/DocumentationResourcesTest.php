<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Resources;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextResourceContents;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Synapse\Documentation\DocumentSearchService;
use Synapse\Documentation\SearchEngine;
use Synapse\Resources\DocumentationResources;

/**
 * DocumentationResources Test Case
 *
 * Tests for documentation resources.
 */
class DocumentationResourcesTest extends TestCase
{
    private ?DocumentationResources $resources = null;

    /**
     * @var \Synapse\Documentation\DocumentSearchService&\PHPUnit\Framework\MockObject\MockObject
     */
    private ?MockObject $mockService = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Create mock service and resources for tests that need them
     */
    private function createMockServiceAndResources(): void
    {
        $this->mockService = $this->createMock(DocumentSearchService::class);
        $this->resources = new DocumentationResources($this->mockService);
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
     * Get the resources instance (with assertion for PHPStan)
     */
    private function getResources(): DocumentationResources
    {
        $this->assertNotNull($this->resources);

        return $this->resources;
    }

    protected function tearDown(): void
    {
        $this->resources = null;
        $this->mockService = null;
        parent::tearDown();
    }

    /**
     * Test searchResource with basic query
     */
    public function testSearchResourceBasicQuery(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'Caching',
                'path' => 'caching.md',
                'source' => 'cakephp-5x',
                'snippet' => 'CakePHP provides a flexible caching...',
                'score' => 3.2,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->with(
                'caching',
                [
                    'limit' => 10,
                    'highlight' => true,
                ],
            )
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('caching');

        $this->assertInstanceOf(TextResourceContents::class, $result);
        $this->assertEquals('docs://search/caching', $result->uri);
        $this->assertEquals('text/markdown', $result->mimeType);
        $this->assertStringContainsString('# Documentation Search: caching', $result->text);
        $this->assertStringContainsString('Found 1 result(s)', $result->text);
        $this->assertStringContainsString('## 1. Caching', $result->text);
    }

    /**
     * Test searchResource with multiple results
     */
    public function testSearchResourceWithMultipleResults(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'First Result',
                'path' => 'first.md',
                'source' => 'cakephp-5x',
                'snippet' => 'First snippet...',
                'score' => 5.0,
            ],
            [
                'title' => 'Second Result',
                'path' => 'second.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Second snippet...',
                'score' => 4.5,
            ],
            [
                'title' => 'Third Result',
                'path' => 'third.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Third snippet...',
                'score' => 4.0,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('test query');
        $content = $result;

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
        $this->createMockServiceAndResources();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->getResources()->searchResource('nonexistent');
        $content = $result;

        $this->assertStringContainsString('# Documentation Search: nonexistent', $content->text);
        $this->assertStringContainsString('No results found', $content->text);
    }

    /**
     * Test searchResource with empty query throws exception
     */
    public function testSearchResourceWithEmptyQueryThrowsException(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $resources = new DocumentationResources($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $resources->searchResource('');
    }

    /**
     * Test searchResource with whitespace-only query throws exception
     */
    public function testSearchResourceWithWhitespaceQueryThrowsException(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $resources = new DocumentationResources($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Search query cannot be empty');

        $resources->searchResource('   ');
    }

    /**
     * Test searchResource handles service exceptions
     */
    public function testSearchResourceHandlesServiceExceptions(): void
    {
        $this->createMockServiceAndResources();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException('Database error'));

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to read documentation resource');

        $this->getResources()->searchResource('query');
    }

    /**
     * Test searchResource formats URI correctly
     */
    public function testSearchResourceFormatsUriCorrectly(): void
    {
        $this->createMockServiceAndResources();

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn([]);

        $result = $this->getResources()->searchResource('test query with spaces');
        $content = $result;

        $this->assertEquals('docs://search/test+query+with+spaces', $content->uri);
    }

    /**
     * Test searchResource includes all result fields
     */
    public function testSearchResourceIncludesAllResultFields(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'Complete Result',
                'path' => 'docs/complete.md',
                'source' => 'cakephp-5x',
                'snippet' => 'This is a complete snippet with all fields.',
                'score' => 7.25,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('complete');
        $content = $result;

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
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'Minimal Result',
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('minimal');
        $content = $result;

        $this->assertStringContainsString('## 1. Minimal Result', $content->text);
        $this->assertStringContainsString('**Source:**', $content->text);
        $this->assertStringContainsString('**Relevance:** 0.00', $content->text);
    }

    /**
     * Test searchResource handles untitled documents
     */
    public function testSearchResourceHandlesUntitledDocuments(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'path' => 'untitled.md',
                'source' => 'cakephp-5x',
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('query');
        $content = $result;

        $this->assertStringContainsString('## 1. Untitled', $content->text);
    }

    /**
     * Test searchResource formats snippets with blockquotes
     */
    public function testSearchResourceFormatsSnippetsWithBlockquotes(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'Result',
                'path' => 'result.md',
                'source' => 'cakephp-5x',
                'snippet' => "Line 1\nLine 2\nLine 3",
                'score' => 1.0,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('query');
        $content = $result;

        $this->assertStringContainsString('> Line 1', $content->text);
        $this->assertStringContainsString('> Line 2', $content->text);
        $this->assertStringContainsString('> Line 3', $content->text);
    }

    /**
     * Test searchResource includes separators between results
     */
    public function testSearchResourceIncludesSeparatorsBetweenResults(): void
    {
        $this->createMockServiceAndResources();

        $expectedResults = [
            [
                'title' => 'First',
                'path' => 'first.md',
                'source' => 'cakephp-5x',
                'snippet' => 'First snippet',
                'score' => 1.0,
            ],
            [
                'title' => 'Second',
                'path' => 'second.md',
                'source' => 'cakephp-5x',
                'snippet' => 'Second snippet',
                'score' => 0.9,
            ],
        ];

        $this->getMockService()->expects($this->once())
            ->method('search')
            ->willReturn($expectedResults);

        $result = $this->getResources()->searchResource('query');
        $content = $result->text;

        $separatorCount = substr_count($content, "---\n\n");
        $this->assertGreaterThanOrEqual(2, $separatorCount);
    }

    /**
     * Test contentResource retrieves full content
     */
    public function testContentResourceRetrievesFullContent(): void
    {
        $this->createMockServiceAndResources();

        $documentData = [
            'id' => 'cakephp-5x::docs/controllers.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/controllers.md',
            'title' => 'Controllers',
            'content' => "# Controllers\n\nLearn about CakePHP controllers.",
            'metadata' => [],
        ];

        $mockSearchEngine = $this->createMock(SearchEngine::class);
        $mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/controllers.md')
            ->willReturn($documentData);

        $this->getMockService()->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($mockSearchEngine);

        $result = $this->getResources()->contentResource('cakephp-5x::docs/controllers.md');

        $this->assertInstanceOf(TextResourceContents::class, $result);
        $this->assertEquals('docs://content/cakephp-5x::docs/controllers.md', $result->uri);
        $this->assertEquals('text/markdown', $result->mimeType);
        $this->assertEquals($documentData['content'], $result->text);
    }

    /**
     * Test contentResource throws exception for empty document ID
     */
    public function testContentResourceThrowsExceptionForEmptyDocId(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $resources = new DocumentationResources($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document ID cannot be empty');

        $resources->contentResource('');
    }

    /**
     * Test contentResource throws exception for nonexistent document
     */
    public function testContentResourceThrowsExceptionForNonexistentDocument(): void
    {
        // Use stubs instead of mocks - no expectations needed
        $stubService = $this->createStub(DocumentSearchService::class);
        $stubSearchEngine = $this->createStub(SearchEngine::class);
        $stubSearchEngine->method('getDocumentById')->willReturn(null);
        $stubService->method('getSearchEngine')->willReturn($stubSearchEngine);
        $resources = new DocumentationResources($stubService);

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Document not found');

        $resources->contentResource('cakephp-5x::docs/missing.md');
    }

    /**
     * Test contentResource returns original markdown with formatting preserved
     */
    public function testContentResourceReturnsOriginalMarkdownWithFormatting(): void
    {
        $this->createMockServiceAndResources();

        $documentData = [
            'id' => 'cakephp-5x::docs/formatting.md',
            'source' => 'cakephp-5x',
            'path' => 'docs/formatting.md',
            'title' => 'Formatting Example',
            'content' => "# Formatting\n\n## Code Examples\n\n```php\n\$config = ['key' => 'value'];\n```\n\n## Lists\n\n1. First item\n2. Second item\n\n## Links\n\nSee [documentation](https://book.cakephp.org).",
            'metadata' => [],
        ];

        $mockSearchEngine = $this->createMock(SearchEngine::class);
        $mockSearchEngine->expects($this->once())
            ->method('getDocumentById')
            ->with('cakephp-5x::docs/formatting.md')
            ->willReturn($documentData);

        $this->getMockService()->expects($this->once())
            ->method('getSearchEngine')
            ->willReturn($mockSearchEngine);

        $result = $this->getResources()->contentResource('cakephp-5x::docs/formatting.md');

        // Verify markdown formatting is preserved in resource
        $content = $result->text;
        $this->assertStringContainsString('```php', $content);
        $this->assertStringContainsString('1. First item', $content);
        $this->assertStringContainsString('[documentation](https://book.cakephp.org)', $content);
        $this->assertStringContainsString('## Code Examples', $content);
    }
}
