<?php
declare(strict_types=1);

namespace Synapse\Documentation;

use Exception;
use RuntimeException;
use SQLite3;
use SQLite3Result;

/**
 * Lightweight SQLite FTS5 Search Engine
 *
 * Provides fulltext search capabilities using SQLite's FTS5 extension.
 */
class SearchEngine
{
    /**
     * SQLite database connection
     */
    private SQLite3 $db;

    /**
     * Whether database is initialized
     */
    private bool $initialized = false;

    /**
     * Constructor
     *
     * @param string $databasePath Path to SQLite database file
     * @throws \RuntimeException If SQLite FTS5 is not available
     */
    public function __construct(private string $databasePath)
    {
        // Ensure directory exists
        $dir = dirname($this->databasePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException(sprintf('Failed to create directory: %s', $dir));
        }

        $this->db = new SQLite3($databasePath);
        $this->db->enableExceptions(true);

        // Check if FTS5 is available
        if (!$this->isFts5Available()) {
            throw new RuntimeException('SQLite FTS5 extension is not available');
        }
    }

    /**
     * Destructor - close database connection
     */
    public function __destruct()
    {
        $this->db->close();
    }

    /**
     * Check if FTS5 extension is available
     */
    private function isFts5Available(): bool
    {
        $result = $this->db->query('PRAGMA compile_options');
        if (!$result) {
            return false;
        }

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (isset($row['compile_options']) && str_contains($row['compile_options'], 'FTS5')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize the search index
     *
     * Creates the FTS5 table and metadata table if they don't exist.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Create FTS5 virtual table for document content
        $this->db->exec("
            CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(
                doc_id UNINDEXED,
                source UNINDEXED,
                path UNINDEXED,
                title,
                headings,
                content,
                tokenize = 'porter unicode61'
            )
        ");

        // Create metadata table for document info
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS documents_meta (
                doc_id TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                path TEXT NOT NULL,
                title TEXT NOT NULL,
                metadata TEXT,
                indexed_at INTEGER NOT NULL
            )
        ");

        // Create index on source for filtering
        $this->db->exec("
            CREATE INDEX IF NOT EXISTS idx_documents_source ON documents_meta(source)
        ");

        $this->initialized = true;
    }

    /**
     * Index a document
     *
     * @param array<string, mixed> $document Document data
     */
    public function indexDocument(array $document): void
    {
        $this->initialize();

        $docId = $document['id'];
        $source = $document['source'];
        $path = $document['path'];
        $title = $document['title'];
        $headings = implode(' ', $document['headings'] ?? []);
        $content = $document['content'];
        $metadata = json_encode($document['metadata'] ?? []);

        // Begin transaction
        $this->db->exec('BEGIN');

        try {
            // Delete existing document if present
            $this->deleteDocument($docId, false);

            // Insert into FTS table
            $stmt = $this->db->prepare("
                INSERT INTO documents_fts (doc_id, source, path, title, headings, content)
                VALUES (:doc_id, :source, :path, :title, :headings, :content)
            ");
            if ($stmt === false) {
                throw new RuntimeException('Failed to prepare FTS insert statement');
            }

            $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
            $stmt->bindValue(':source', $source, SQLITE3_TEXT);
            $stmt->bindValue(':path', $path, SQLITE3_TEXT);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':headings', $headings, SQLITE3_TEXT);
            $stmt->bindValue(':content', $content, SQLITE3_TEXT);
            $stmt->execute();

            // Insert into metadata table
            $stmt = $this->db->prepare("
                INSERT INTO documents_meta (doc_id, source, path, title, metadata, indexed_at)
                VALUES (:doc_id, :source, :path, :title, :metadata, :indexed_at)
            ");
            if ($stmt === false) {
                throw new RuntimeException('Failed to prepare metadata insert statement');
            }

            $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
            $stmt->bindValue(':source', $source, SQLITE3_TEXT);
            $stmt->bindValue(':path', $path, SQLITE3_TEXT);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':metadata', $metadata, SQLITE3_TEXT);
            $stmt->bindValue(':indexed_at', time(), SQLITE3_INTEGER);
            $stmt->execute();

            $this->db->exec('COMMIT');
        } catch (Exception $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * Index multiple documents in batch
     *
     * @param array<array<string, mixed>> $documents List of documents
     */
    public function indexBatch(array $documents): void
    {
        $this->initialize();

        $this->db->exec('BEGIN');

        try {
            foreach ($documents as $document) {
                $source = $document['source'];
                $path = $document['path'];
                $docId = $document['id'] ?? sprintf('%s::%s', $source, $path);
                $title = $document['title'];
                $headings = implode(' ', $document['headings'] ?? []);
                $content = $document['content'];
                $metadata = json_encode($document['metadata'] ?? []);

                // Delete existing document if present (without transaction)
                $stmt = $this->db->prepare('DELETE FROM documents_fts WHERE doc_id = :doc_id');
                if ($stmt !== false) {
                    $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                    $stmt->execute();
                }

                $stmt = $this->db->prepare('DELETE FROM documents_meta WHERE doc_id = :doc_id');
                if ($stmt !== false) {
                    $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                    $stmt->execute();
                }

                // Insert into FTS table
                $stmt = $this->db->prepare("
                    INSERT INTO documents_fts (doc_id, source, path, title, headings, content)
                    VALUES (:doc_id, :source, :path, :title, :headings, :content)
                ");
                if ($stmt === false) {
                    throw new RuntimeException('Failed to prepare FTS insert statement');
                }

                $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                $stmt->bindValue(':source', $source, SQLITE3_TEXT);
                $stmt->bindValue(':path', $path, SQLITE3_TEXT);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':headings', $headings, SQLITE3_TEXT);
                $stmt->bindValue(':content', $content, SQLITE3_TEXT);
                $stmt->execute();

                // Insert into metadata table
                $stmt = $this->db->prepare("
                    INSERT INTO documents_meta (doc_id, source, path, title, metadata, indexed_at)
                    VALUES (:doc_id, :source, :path, :title, :metadata, :indexed_at)
                ");
                if ($stmt === false) {
                    throw new RuntimeException('Failed to prepare metadata insert statement');
                }

                $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                $stmt->bindValue(':source', $source, SQLITE3_TEXT);
                $stmt->bindValue(':path', $path, SQLITE3_TEXT);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':metadata', $metadata, SQLITE3_TEXT);
                $stmt->bindValue(':indexed_at', time(), SQLITE3_INTEGER);
                $stmt->execute();
            }

            $this->db->exec('COMMIT');
        } catch (Exception $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * Delete a document from the index
     *
     * @param string $docId Document ID
     * @param bool $useTransaction Whether to use transaction
     */
    public function deleteDocument(string $docId, bool $useTransaction = true): void
    {
        if ($useTransaction) {
            $this->db->exec('BEGIN');
        }

        try {
            $stmt = $this->db->prepare('DELETE FROM documents_fts WHERE doc_id = :doc_id');
            if ($stmt !== false) {
                $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                $stmt->execute();
            }

            $stmt = $this->db->prepare('DELETE FROM documents_meta WHERE doc_id = :doc_id');
            if ($stmt !== false) {
                $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
                $stmt->execute();
            }

            if ($useTransaction) {
                $this->db->exec('COMMIT');
            }
        } catch (Exception $exception) {
            if ($useTransaction) {
                $this->db->exec('ROLLBACK');
            }

            throw $exception;
        }
    }

    /**
     * Clear all documents from the index
     */
    public function clear(): void
    {
        $this->initialize();

        $this->db->exec('BEGIN');
        try {
            $this->db->exec('DELETE FROM documents_fts');
            $this->db->exec('DELETE FROM documents_meta');
            $this->db->exec('COMMIT');
        } catch (Exception $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * Clear documents for a specific source
     *
     * @param string $source Source key
     */
    public function clearSource(string $source): void
    {
        $this->initialize();

        $this->db->exec('BEGIN');
        try {
            $stmt = $this->db->prepare('DELETE FROM documents_fts WHERE source = :source');
            if ($stmt !== false) {
                $stmt->bindValue(':source', $source, SQLITE3_TEXT);
                $stmt->execute();
            }

            $stmt = $this->db->prepare('DELETE FROM documents_meta WHERE source = :source');
            if ($stmt !== false) {
                $stmt->bindValue(':source', $source, SQLITE3_TEXT);
                $stmt->execute();
            }

            $this->db->exec('COMMIT');
        } catch (Exception $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * Search documents
     *
     * @param string $query Search query
     * @param array<string, mixed> $options Search options
     *   - limit: int - Maximum number of results (default: 10)
     *   - sources: array - Filter by source keys
     *   - highlight: bool - Enable highlighting (default: true)
     *   - fuzzy: bool - Enable fuzzy matching (default: false)
     * @return array<array<string, mixed>> Search results
     */
    public function search(string $query, array $options = []): array
    {
        $this->initialize();

        // Validate query
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = $options['limit'] ?? 10;
        $sources = $options['sources'] ?? [];
        $highlight = $options['highlight'] ?? true;
        $fuzzy = $options['fuzzy'] ?? false;

        // Apply fuzzy matching if enabled
        if ($fuzzy) {
            $query = $this->makeFuzzyQuery($query);
        }

        // Escape special FTS5 characters if not using fuzzy mode
        if (!$fuzzy) {
            $query = $this->escapeFtsQuery($query);
        }

        // Build the search query
        $sql = "
            SELECT
                fts.doc_id,
                fts.source,
                fts.path,
                fts.title,
                meta.metadata,
                bm25(documents_fts) as score
        ";

        if ($highlight) {
            $sql .= ",
                highlight(documents_fts, 3, '<mark>', '</mark>') as title_highlight,
                snippet(documents_fts, 5, '<mark>', '</mark>', '...', 32) as snippet
            ";
        }

        $sql .= "
            FROM documents_fts fts
            JOIN documents_meta meta ON fts.doc_id = meta.doc_id
            WHERE documents_fts MATCH :query
        ";

        if (!empty($sources)) {
            $placeholders = [];
            foreach ($sources as $i => $source) {
                $placeholders[] = ':source' . $i;
            }

            $sql .= sprintf(' AND meta.source IN (%s)', implode(',', $placeholders));
        }

        $sql .= "
            ORDER BY score DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bindValue(':query', $query, SQLITE3_TEXT);
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);

        if (!empty($sources)) {
            foreach ($sources as $i => $source) {
                $stmt->bindValue(':source' . $i, $source, SQLITE3_TEXT);
            }
        }

        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) {
            return [];
        }

        $results = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = [
                'id' => $row['doc_id'],
                'source' => $row['source'],
                'path' => $row['path'],
                'title' => $row['title'],
                'metadata' => json_decode($row['metadata'], true),
                'score' => abs($row['score']),
                'title_highlight' => $highlight ? ($row['title_highlight'] ?? $row['title']) : null,
                'snippet' => $highlight ? ($row['snippet'] ?? '') : null,
            ];
        }

        return $results;
    }

    /**
     * Get total document count
     */
    public function getDocumentCount(): int
    {
        $this->initialize();

        $result = $this->db->query('SELECT COUNT(*) as count FROM documents_meta');
        if (!$result) {
            return 0;
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);

        return (int)($row['count'] ?? 0);
    }

    /**
     * Get document count by source
     *
     * @return array<string, int>
     */
    public function getDocumentCountBySource(): array
    {
        $this->initialize();

        $result = $this->db->query('
            SELECT source, COUNT(*) as count
            FROM documents_meta
            GROUP BY source
        ');

        if (!$result) {
            return [];
        }

        $counts = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $counts[$row['source']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Optimize the search index
     */
    public function optimize(): void
    {
        $this->initialize();

        $this->db->exec("INSERT INTO documents_fts(documents_fts) VALUES('optimize')");
        $this->db->exec('VACUUM');
    }

    /**
     * Convert query to fuzzy search query using prefix matching
     *
     * Splits query into terms and adds prefix wildcard (*) to each term
     * for approximate matching. Also handles phrases in quotes.
     *
     * @param string $query Original search query
     */
    private function makeFuzzyQuery(string $query): string
    {
        // Handle quoted phrases - preserve them as-is
        $phrases = [];
        $processedQuery = preg_replace_callback('/"([^"]+)"/', function (array $matches) use (&$phrases): string {
            $key = '___PHRASE_' . count($phrases) . '___';
            $phrases[$key] = '"' . $matches[1] . '"';

            return $key;
        }, $query);

        if ($processedQuery === null) {
            $processedQuery = $query;
        }

        // Split remaining query into words
        $words = preg_split('/\s+/', trim($processedQuery), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return $query;
        }

        $fuzzyTerms = [];
        foreach ($words as $word) {
            // Check if this is a phrase placeholder
            if (isset($phrases[$word])) {
                $fuzzyTerms[] = $phrases[$word];
                continue;
            }

            // Skip very short words (less than 3 characters)
            if (strlen($word) < 3) {
                $fuzzyTerms[] = $word;
                continue;
            }

            // Add prefix wildcard for fuzzy matching
            // This allows partial word matches (e.g., "cake*" matches "cakephp", "cakes", etc.)
            $fuzzyTerms[] = $word . '*';
        }

        return implode(' OR ', $fuzzyTerms);
    }

    /**
     * Escape special FTS5 query characters
     *
     * Escapes characters that have special meaning in FTS5 queries to prevent syntax errors.
     *
     * @param string $query Query to escape
     * @return string Escaped query
     */
    private function escapeFtsQuery(string $query): string
    {
        // FTS5 special characters that need escaping
        // We'll wrap the query in quotes to treat it as a phrase if it contains special chars
        $specialChars = ['@', '#', '$', '%', '^', '&', '*', '(', ')', '[', ']', '{', '}', '\\', '|', '<', '>'];

        foreach ($specialChars as $char) {
            if (str_contains($query, $char)) {
                // Remove special characters rather than trying to escape them
                $query = str_replace($specialChars, ' ', $query);
                break;
            }
        }

        // Clean up multiple spaces
        $query = preg_replace('/\s+/', ' ', $query);

        return trim($query ?? '');
    }
}
