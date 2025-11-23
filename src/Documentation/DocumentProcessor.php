<?php
declare(strict_types=1);

namespace Synapse\Documentation;

use Synapse\Documentation\Git\Repository;

/**
 * Processes markdown documentation files for indexing
 *
 * Extracts frontmatter, title, headings, and prepares content for search indexing.
 */
class DocumentProcessor
{
    /**
     * Process a markdown file from a repository
     *
     * @param \Synapse\Documentation\Git\Repository $repository Repository instance
     * @param string $relativePath File path relative to repository root
     * @param string $sourceKey Source configuration key
     * @return array<string, mixed>|null Processed document data or null if file doesn't exist
     */
    public function process(Repository $repository, string $relativePath, string $sourceKey): ?array
    {
        $content = $repository->readFile($relativePath);
        if ($content === null) {
            return null;
        }

        return $this->processContent($content, $relativePath, $sourceKey);
    }

    /**
     * Process markdown content
     *
     * @param string $content Markdown content
     * @param string $relativePath Relative file path
     * @param string $sourceKey Source configuration key
     * @return array<string, mixed> Processed document data
     */
    public function processContent(
        string $content,
        string $relativePath,
        string $sourceKey,
    ): array {
        // Extract frontmatter if present
        $frontmatter = $this->extractFrontmatter($content);
        $contentWithoutFrontmatter = $frontmatter['content'];

        // Extract title
        $title = $this->extractTitle($contentWithoutFrontmatter, $relativePath);

        // Extract headings
        $headings = $this->extractHeadings($contentWithoutFrontmatter);

        // Clean content for indexing (used for FTS search)
        $cleanContent = $this->cleanContent($contentWithoutFrontmatter);

        // Generate unique ID
        $id = $this->generateId($sourceKey, $relativePath);

        return [
            'id' => $id,
            'source' => $sourceKey,
            'path' => $relativePath,
            'title' => $title,
            'headings' => $headings,
            'content' => $cleanContent,
            'original_content' => $contentWithoutFrontmatter,
            'metadata' => array_merge(
                $frontmatter['data'],
                [
                    'path' => $relativePath,
                    'source' => $sourceKey,
                ],
            ),
        ];
    }

    /**
     * Extract YAML frontmatter from markdown content
     *
     * @param string $content Markdown content
     * @return array{data: array<string, string>, content: string}
     */
    private function extractFrontmatter(string $content): array
    {
        $data = [];
        $contentWithoutFrontmatter = $content;

        // Check for YAML frontmatter (--- at start and end)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $yamlContent = $matches[1];
            $contentWithoutFrontmatter = $matches[2];

            // Parse YAML (simple key: value pairs)
            $lines = explode("\n", $yamlContent);
            foreach ($lines as $line) {
                if (preg_match('/^([^:]+):\s*(.*)$/', trim($line), $lineMatches)) {
                    $key = trim($lineMatches[1]);
                    $value = trim($lineMatches[2], " \t\n\r\0\x0B\"'");
                    $data[$key] = $value;
                }
            }
        }

        return [
            'data' => $data,
            'content' => $contentWithoutFrontmatter,
        ];
    }

    /**
     * Extract title from markdown content
     *
     * @param string $content Markdown content
     * @param string $path File path (fallback if no title found)
     */
    private function extractTitle(string $content, string $path): string
    {
        // Look for first # heading
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        // Fallback: use filename without extension
        $filename = basename($path, '.md');

        return ucfirst(str_replace(['-', '_'], ' ', $filename));
    }

    /**
     * Extract all headings from markdown content
     *
     * @param string $content Markdown content
     * @return array<string>
     */
    private function extractHeadings(string $content): array
    {
        $headings = [];

        // Match all markdown headings (# through ######)
        if (preg_match_all('/^#{1,6}\s+(.+)$/m', $content, $matches)) {
            foreach ($matches[1] as $heading) {
                $headings[] = trim($heading);
            }
        }

        return $headings;
    }

    /**
     * Clean markdown content for indexing
     *
     * Removes code blocks, HTML tags, and excessive whitespace.
     *
     * @param string $content Markdown content
     * @return string Cleaned content
     */
    private function cleanContent(string $content): string
    {
        // Remove code blocks (```...```)
        $content = (string)preg_replace('/```[\s\S]*?```/m', '', $content);

        // Remove inline code (`...`)
        $content = (string)preg_replace('/`[^`]+`/', '', $content);

        // Remove HTML comments
        $content = (string)preg_replace('/<!--[\s\S]*?-->/', '', $content);

        // Remove HTML tags but keep content
        $content = strip_tags($content);

        // Remove markdown links but keep text [text](url) -> text
        $content = (string)preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $content);

        // Remove image syntax ![alt](url)
        $content = (string)preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $content);

        // Remove headings markers
        $content = (string)preg_replace('/^#{1,6}\s+/m', '', $content);

        // Remove horizontal rules
        $content = (string)preg_replace('/^(-{3,}|\*{3,}|_{3,})$/m', '', $content);

        // Remove list markers
        $content = (string)preg_replace('/^[\*\-\+]\s+/m', '', $content);
        $content = (string)preg_replace('/^\d+\.\s+/m', '', $content);

        // Normalize whitespace
        $content = (string)preg_replace('/\n{3,}/', "\n\n", $content);
        $content = (string)preg_replace('/[ \t]+/', ' ', $content);

        return trim($content);
    }

    /**
     * Generate unique document ID
     *
     * @param string $sourceKey Source configuration key
     * @param string $path File path
     */
    private function generateId(string $sourceKey, string $path): string
    {
        return sprintf('%s::%s', $sourceKey, $path);
    }

    /**
     * Batch process multiple files
     *
     * @param \Synapse\Documentation\Git\Repository $repository Repository instance
     * @param array<string> $files List of file paths relative to repository root
     * @param string $sourceKey Source configuration key
     * @return array<array<string, mixed>> List of processed documents
     */
    public function processBatch(Repository $repository, array $files, string $sourceKey): array
    {
        $documents = [];

        foreach ($files as $file) {
            $doc = $this->process($repository, $file, $sourceKey);
            if ($doc !== null) {
                $documents[] = $doc;
            }
        }

        return $documents;
    }
}
