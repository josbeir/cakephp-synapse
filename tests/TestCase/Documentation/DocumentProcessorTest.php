<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Documentation;

use Cake\TestSuite\TestCase;
use Synapse\Documentation\DocumentProcessor;
use Synapse\Documentation\Git\Repository;

/**
 * DocumentProcessor Test Case
 *
 * Tests for markdown document processing and parsing.
 */
class DocumentProcessorTest extends TestCase
{
    /**
     * Test subject
     */
    protected DocumentProcessor $DocumentProcessor;

    /**
     * Path to test markdown files
     */
    protected string $docsPath;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->DocumentProcessor = new DocumentProcessor();
        $this->docsPath = ROOT . DS . 'docs' . DS;
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Helper to read a test markdown file
     */
    protected function readTestFile(string $filename): string
    {
        $content = file_get_contents($this->docsPath . $filename);
        $this->assertNotFalse($content, sprintf('Test file %s not found', $filename));

        return $content;
    }

    /**
     * Test processContent with simple markdown
     */
    public function testProcessContentSimple(): void
    {
        $content = $this->readTestFile('simple.md');
        $result = $this->DocumentProcessor->processContent($content, 'simple.md', '/absolute/path/simple.md', 'test-source');

        $this->assertEquals('test-source::simple.md', $result['id']);
        $this->assertEquals('test-source', $result['source']);
        $this->assertEquals('/absolute/path/simple.md', $result['path']);
        $this->assertEquals('simple.md', $result['relative_path']);
        $this->assertEquals('Test Document', $result['title']);
        $this->assertIsArray($result['headings']);
        $this->assertCount(3, $result['headings']);
        $this->assertEquals('Test Document', $result['headings'][0]);
        $this->assertEquals('Section 1', $result['headings'][1]);
        $this->assertEquals('Section 2', $result['headings'][2]);
        $this->assertIsString($result['content']);
        $this->assertStringContainsString('simple test document', $result['content']);
    }

    /**
     * Test processContent with frontmatter
     */
    public function testProcessContentWithFrontmatter(): void
    {
        $content = $this->readTestFile('with-frontmatter.md');
        $result = $this->DocumentProcessor->processContent($content, 'frontmatter.md', '/absolute/path/frontmatter.md', 'test-source');

        $this->assertEquals('Document Title', $result['title']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('Custom Title', $result['metadata']['title']);
        $this->assertEquals('Test Author', $result['metadata']['author']);
        $this->assertEquals('2024-01-01', $result['metadata']['date']);
        $this->assertEquals('test, documentation', $result['metadata']['tags']);
        $this->assertStringContainsString('YAML frontmatter', $result['content']);
    }

    /**
     * Test extractTitle falls back to filename
     */
    public function testExtractTitleFallback(): void
    {
        $content = 'Just some content without a heading';
        $result = $this->DocumentProcessor->processContent($content, 'intro/getting-started.md', '/absolute/path/intro/getting-started.md', 'test-source');

        $this->assertEquals('Getting started', $result['title']);
    }

    /**
     * Test cleanContent removes code blocks
     */
    public function testCleanContentRemovesCodeBlocks(): void
    {
        $content = $this->readTestFile('with-code.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        // Code blocks should be removed
        $this->assertStringNotContainsString('```', $result['content']);
        $this->assertStringNotContainsString('declare(strict_types=1)', $result['content']);
        $this->assertStringNotContainsString('UsersController', $result['content']);
        $this->assertStringNotContainsString('console.log', $result['content']);
        $this->assertStringNotContainsString('composer install', $result['content']);

        // Inline code should be removed
        $this->assertStringNotContainsString('$variable', $result['content']);
        $this->assertStringNotContainsString('echo "test"', $result['content']);
        $this->assertStringNotContainsString('const x = 10', $result['content']);

        // Regular content should remain
        $this->assertStringContainsString('Regular Content', $result['content']);
        $this->assertStringContainsString('important text content', $result['content']);
    }

    /**
     * Test cleanContent removes HTML
     */
    public function testCleanContentRemovesHtml(): void
    {
        $content = $this->readTestFile('with-html.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        // HTML tags should be removed
        $this->assertStringNotContainsString('<div', $result['content']);
        $this->assertStringNotContainsString('<strong>', $result['content']);
        $this->assertStringNotContainsString('<em>', $result['content']);
        $this->assertStringNotContainsString('<p class="warning">', $result['content']);

        // HTML comments should be removed
        $this->assertStringNotContainsString('<!-- This is an HTML comment', $result['content']);
        $this->assertStringNotContainsString('Multi-line comment', $result['content']);

        // Text content should remain
        $this->assertStringContainsString('This is a note with bold text and italic text', $result['content']);
        $this->assertStringContainsString('Warning message with highlighted content', $result['content']);
        $this->assertStringContainsString('Regular text should remain visible', $result['content']);
    }

    /**
     * Test cleanContent handles markdown links
     */
    public function testCleanContentHandlesLinks(): void
    {
        $content = $this->readTestFile('with-links.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        // Link text should remain
        $this->assertStringContainsString('this link', $result['content']);
        $this->assertStringContainsString('CakePHP documentation', $result['content']);
        $this->assertStringContainsString('GitHub', $result['content']);

        // URLs should be removed
        $this->assertStringNotContainsString('https://example.com', $result['content']);
        $this->assertStringNotContainsString('[this link]', $result['content']);
        $this->assertStringNotContainsString('(https://', $result['content']);

        // Image syntax should be removed
        $this->assertStringNotContainsString('![', $result['content']);
        $this->assertStringNotContainsString('screenshot.png', $result['content']);
        $this->assertStringNotContainsString('logo.png', $result['content']);
    }

    /**
     * Test cleanContent removes list markers
     */
    public function testCleanContentRemovesListMarkers(): void
    {
        $content = $this->readTestFile('with-lists.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        // List content should remain
        $this->assertStringContainsString('Item 1', $result['content']);
        $this->assertStringContainsString('First item', $result['content']);
        $this->assertStringContainsString('First step', $result['content']);
        $this->assertStringContainsString('Alpha', $result['content']);

        // List markers should be removed
        $this->assertStringNotContainsString('* Item', $result['content']);
        $this->assertStringNotContainsString('- First', $result['content']);
        $this->assertStringNotContainsString('+ Alpha', $result['content']);
        $this->assertStringNotContainsString('1. First step', $result['content']);
        $this->assertStringNotContainsString('2. Second step', $result['content']);

        // Horizontal rules should be removed
        $this->assertStringNotContainsString('---', $result['content']);
        $this->assertStringNotContainsString('***', $result['content']);
        $this->assertStringNotContainsString('___', $result['content']);
    }

    /**
     * Test extractHeadings
     */
    public function testExtractHeadings(): void
    {
        $content = $this->readTestFile('simple.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        $this->assertCount(3, $result['headings']);
        $this->assertEquals('Test Document', $result['headings'][0]);
        $this->assertEquals('Section 1', $result['headings'][1]);
        $this->assertEquals('Section 2', $result['headings'][2]);
    }

    /**
     * Test extractHeadings with complex document
     */
    public function testExtractHeadingsComplex(): void
    {
        $content = $this->readTestFile('with-frontmatter.md');
        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        $this->assertCount(3, $result['headings']);
        $this->assertEquals('Document Title', $result['headings'][0]);
        $this->assertEquals('Introduction', $result['headings'][1]);
        $this->assertEquals('Features', $result['headings'][2]);
    }

    /**
     * Test generateId creates unique identifiers
     */
    public function testGenerateIdFormat(): void
    {
        $result1 = $this->DocumentProcessor->processContent('# Test', 'doc1.md', '/absolute/path/doc1.md', 'source1');
        $result2 = $this->DocumentProcessor->processContent('# Test', 'doc2.md', '/absolute/path/doc2.md', 'source1');
        $result3 = $this->DocumentProcessor->processContent('# Test', 'doc1.md', '/absolute/path/doc1.md', 'source2');

        $this->assertEquals('source1::doc1.md', $result1['id']);
        $this->assertEquals('source1::doc2.md', $result2['id']);
        $this->assertEquals('source2::doc1.md', $result3['id']);

        // All IDs should be unique
        $this->assertNotEquals($result1['id'], $result2['id']);
        $this->assertNotEquals($result1['id'], $result3['id']);
        $this->assertNotEquals($result2['id'], $result3['id']);
    }

    /**
     * Test process with null repository file
     */
    public function testProcessWithNonExistentFile(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('readFile')->willReturn(null);

        $result = $this->DocumentProcessor->process($repository, 'non-existent.md', 'test-source');

        $this->assertNull($result);
    }

    /**
     * Test process with valid repository file
     */
    public function testProcessWithValidFile(): void
    {
        $content = $this->readTestFile('simple.md');
        $repository = $this->createMock(Repository::class);
        $repository->method('readFile')->willReturn($content);

        $result = $this->DocumentProcessor->process($repository, 'simple.md', 'test-source');

        $this->assertNotNull($result);
        $this->assertEquals('Test Document', $result['title']);
        $this->assertEquals('test-source::simple.md', $result['id']);
    }

    /**
     * Test processBatch with multiple files
     */
    public function testProcessBatch(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('readFile')->willReturnMap([
            ['simple.md', $this->readTestFile('simple.md')],
            ['with-frontmatter.md', $this->readTestFile('with-frontmatter.md')],
            ['non-existent.md', null],
            ['with-code.md', $this->readTestFile('with-code.md')],
        ]);

        $files = ['simple.md', 'with-frontmatter.md', 'non-existent.md', 'with-code.md'];
        $results = $this->DocumentProcessor->processBatch($repository, $files, 'test-source');

        // Should only return 3 documents (non-existent.md returns null)
        $this->assertCount(3, $results);
        $this->assertEquals('Test Document', $results[0]['title']);
        $this->assertEquals('Document Title', $results[1]['title']);
        $this->assertEquals('Code Examples', $results[2]['title']);
    }

    /**
     * Test processBatch with empty array
     */
    public function testProcessBatchEmpty(): void
    {
        $repository = $this->createMock(Repository::class);
        $results = $this->DocumentProcessor->processBatch($repository, [], 'test-source');

        $this->assertEmpty($results);
    }

    /**
     * Test metadata includes path and source
     */
    public function testMetadataIncludesPathAndSource(): void
    {
        $content = $this->readTestFile('simple.md');
        $result = $this->DocumentProcessor->processContent($content, 'test/file.md', '/absolute/path/test/file.md', 'test-source');

        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('/absolute/path/test/file.md', $result['metadata']['path']);
        $this->assertEquals('test/file.md', $result['metadata']['relative_path']);
        $this->assertEquals('test-source', $result['metadata']['source']);
    }

    /**
     * Test cleanContent normalizes whitespace
     */
    public function testCleanContentNormalizesWhitespace(): void
    {
        $content = <<<'MD'
# Title


Multiple


blank lines.

And    multiple    spaces.
MD;

        $result = $this->DocumentProcessor->processContent($content, 'test.md', '/absolute/path/test.md', 'test-source');

        // Should not have more than 2 consecutive newlines
        $this->assertStringNotContainsString("\n\n\n", $result['content']);
        // Multiple spaces should be collapsed to single space
        $this->assertStringContainsString('multiple spaces', $result['content']);
        $this->assertStringNotContainsString('multiple    spaces', $result['content']);
    }
}
