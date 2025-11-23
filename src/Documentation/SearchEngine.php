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
     * Schema definition for all tables
     *
     * Defines the complete database schema including columns, types, and indexes.
     * Used for both table creation and schema validation.
     */
    private const SCHEMA_DEFINITION = [
        'documents_meta' => [
            'columns' => [
                'doc_id' => 'TEXT PRIMARY KEY',
                'source' => 'TEXT NOT NULL',
                'path' => 'TEXT NOT NULL',
                'title' => 'TEXT NOT NULL',
                'metadata' => 'TEXT',
                'original_content' => 'TEXT',
                'indexed_at' => 'INTEGER NOT NULL',
            ],
            'indexes' => [
                'idx_documents_source' => 'source',
            ],
        ],
        'documents_fts' => [
            'type' => 'fts5',
            'columns' => [
                'doc_id' => 'UNINDEXED',
                'source' => 'UNINDEXED',
                'path' => 'UNINDEXED',
                'title' => null,
                'headings' => null,
                'content' => null,
            ],
            'options' => "tokenize = 'porter unicode61'",
        ],
    ];

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
    public function __construct(
        private string $databasePath,
    ) {
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

        // Initialize tables and schema (following CakePHP pattern)
        $this->initialize();
    }

    /**
     * Destructor - close database connection
     */
    public function __destruct()
    {
        // @phpstan-ignore-next-line
        if (isset($this->db)) {
            $this->db->close();
        }
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
            if (
                is_array($row) &&
                isset($row['compile_options']) &&
                is_string($row['compile_options']) &&
                str_contains($row['compile_options'], 'ENABLE_FTS5')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Initialize the search index
     *
     * Creates tables from schema definition if they don't exist.
     * Validates existing schema and rebuilds if mismatch detected.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Create tables if they don't exist
        $this->createTables();

        // Validate schema matches expected definition
        if (!$this->validateSchema()) {
            // Schema mismatch - destroy and recreate
            $this->destroy();
            $this->createTables();
        }

        $this->initialized = true;
    }

    /**
     * Create all database tables from schema definition
     */
    private function createTables(): void
    {
        foreach (self::SCHEMA_DEFINITION as $table => $definition) {
            // @phpstan-ignore-next-line (type is always fts5 when isset, this is intentional)
            if (isset($definition['type']) && $definition['type'] === 'fts5') { // @phpstan-ignore-line
                $this->createFtsTable($table, $definition);
            } else {
                $this->createRegularTable($table, $definition);
            }
        }
    }

    /**
     * Create a regular SQLite table
     *
     * @param string $table Table name
     * @param array<string, mixed> $definition Table definition
     */
    private function createRegularTable(string $table, array $definition): void
    {
        // Build column definitions
        $columnsSql = [];
        foreach ($definition['columns'] as $name => $type) {
            $columnsSql[] = sprintf('%s %s', $name, $type);
        }

        // Create table
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s)',
            $table,
            implode(', ', $columnsSql),
        );

        $this->db->exec($sql);

        // Create indexes if defined
        if (isset($definition['indexes'])) {
            foreach ($definition['indexes'] as $indexName => $column) {
                $this->db->exec(sprintf(
                    'CREATE INDEX IF NOT EXISTS %s ON %s(%s)',
                    $indexName,
                    $table,
                    $column,
                ));
            }
        }
    }

    /**
     * Create an FTS5 virtual table
     *
     * @param string $table Table name
     * @param array<string, mixed> $definition Table definition
     */
    private function createFtsTable(string $table, array $definition): void
    {
        // Build column definitions
        $columnsSql = [];
        foreach ($definition['columns'] as $name => $modifier) {
            if ($modifier === null) {
                $columnsSql[] = $name;
            } else {
                $columnsSql[] = sprintf('%s %s', $name, $modifier);
            }
        }

        // Create FTS5 virtual table
        $options = $definition['options'] ?? '';

        $sql = sprintf(
            'CREATE VIRTUAL TABLE IF NOT EXISTS %s USING fts5(%s, %s)',
            $table,
            implode(', ', $columnsSql),
            $options,
        );

        $this->db->exec($sql);
    }

    /**
     * Validate that existing schema matches expected definition
     */
    private function validateSchema(): bool
    {
        foreach (self::SCHEMA_DEFINITION as $table => $definition) {
            // Check if table exists
            if (!$this->tableExists($table)) {
                return false;
            }

            // Validate all expected columns exist
            $actualColumns = $this->getTableColumns($table);
            $expectedColumns = array_keys($definition['columns']);

            foreach ($expectedColumns as $column) {
                if (!in_array($column, $actualColumns, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $table): bool
    {
        $result = $this->db->querySingle(
            sprintf(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='%s'",
                $table,
            ),
        );

        return is_string($result);
    }

    /**
     * Get list of column names for a table
     *
     * @return array<string>
     */
    private function getTableColumns(string $table): array
    {
        $result = $this->db->query(sprintf('PRAGMA table_info(%s)', $table));
        $columns = [];

        if ($result instanceof SQLite3Result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                if (is_array($row) && isset($row['name'])) {
                    $columns[] = (string)$row['name'];
                }
            }
        }

        return $columns;
    }

    /**
     * Index multiple documents in batch
     *
     * @param array<array<string, mixed>> $documents List of documents
     */
    public function indexBatch(array $documents): void
    {
        $this->db->exec('BEGIN');

        try {
            foreach ($documents as $document) {
                $this->indexDocument($document);
            }

            $this->db->exec('COMMIT');
        } catch (Exception $exception) {
            $this->db->exec('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * Index a single document
     *
     * This method handles the core indexing logic without transaction management.
     * When called from indexBatch(), the transaction is managed by the batch method.
     * When called directly, you may want to wrap it in your own transaction if needed.
     *
     * @param array<string, mixed> $document Document data
     */
    public function indexDocument(array $document): void
    {
        $docId = (string)($document['id'] ?? '');
        $source = (string)($document['source'] ?? '');
        $path = (string)($document['path'] ?? '');
        $title = (string)($document['title'] ?? '');
        $headings = implode(' ', $document['headings'] ?? []);
        $content = (string)($document['content'] ?? '');
        $originalContent = (string)($document['original_content'] ?? $content);
        $metadata = json_encode($document['metadata'] ?? []) ?: '{}';

        // Generate doc ID if not present
        if ($docId === '') {
            $docId = sprintf('%s::%s', $source, $path);
        }

        // Delete existing document if present
        $this->deleteDocumentInternal($docId);

        // Insert into FTS table (using cleaned content for search)
        $this->insertIntoFtsTable($docId, $source, $path, $title, $headings, $content);

        // Insert into metadata table (including original markdown content)
        $this->insertIntoMetaTable($docId, $source, $path, $title, $metadata, $originalContent);
    }

    /**
     * Delete a document without transaction management
     *
     * @param string $docId Document ID
     */
    private function deleteDocumentInternal(string $docId): void
    {
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
    }

    /**
     * Insert document into FTS table
     *
     * @param string $docId Document ID
     * @param string $source Source
     * @param string $path Path
     * @param string $title Title
     * @param string $headings Headings
     * @param string $content Content
     */
    private function insertIntoFtsTable(
        string $docId,
        string $source,
        string $path,
        string $title,
        string $headings,
        string $content,
    ): void {
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
    }

    /**
     * Insert document into metadata table
     *
     * @param string $docId Document ID
     * @param string $source Source
     * @param string $path Path
     * @param string $title Title
     * @param string $metadata Metadata JSON
     * @param string $originalContent Original markdown content
     */
    private function insertIntoMetaTable(
        string $docId,
        string $source,
        string $path,
        string $title,
        string $metadata,
        string $originalContent,
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO documents_meta (doc_id, source, path, title, metadata, original_content, indexed_at)
            VALUES (:doc_id, :source, :path, :title, :metadata, :original_content, :indexed_at)
        ");
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare metadata insert statement');
        }

        $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
        $stmt->bindValue(':source', $source, SQLITE3_TEXT);
        $stmt->bindValue(':path', $path, SQLITE3_TEXT);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':metadata', $metadata, SQLITE3_TEXT);
        $stmt->bindValue(':original_content', $originalContent, SQLITE3_TEXT);
        $stmt->bindValue(':indexed_at', time(), SQLITE3_INTEGER);
        $stmt->execute();
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

        if (is_array($sources) && $sources !== []) {
            $placeholders = [];
            foreach (array_keys($sources) as $i) {
                if (is_int($i) || is_string($i)) { // @phpstan-ignore-line
                    $placeholders[] = ':source' . $i;
                }
            }

            $sql .= sprintf(' AND meta.source IN (%s)', implode(', ', $placeholders));
        }

        $sql .= "
            ORDER BY score ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            return [];
        }

        $stmt->bindValue(':query', $query, SQLITE3_TEXT);
        $stmt->bindValue(':limit', is_int($limit) ? $limit : 10, SQLITE3_INTEGER);

        if (is_array($sources) && $sources !== []) {
            foreach ($sources as $i => $source) {
                if (is_string($source)) {
                    $stmt->bindValue(':source' . $i, $source, SQLITE3_TEXT);
                }
            }
        }

        $result = $stmt->execute();
        if (!$result instanceof SQLite3Result) {
            return [];
        }

        $results = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }

            $metadataArray = json_decode((string)$row['metadata'], true);

            $results[] = [
                'id' => (string)$row['doc_id'],
                'source' => (string)$row['source'],
                'path' => (string)$row['path'],
                'title' => (string)$row['title'],
                'metadata' => is_array($metadataArray) ? $metadataArray : [],
                // Convert BM25 scores to absolute values for better intuitiveness
                // BM25 returns negative scores (more negative = better match)
                // Absolute values make "higher = better" which is more intuitive for users/LLMs
                'score' => abs((float)$row['score']),
                'title_highlight' => $highlight ? (string)($row['title_highlight'] ?? $row['title']) : null,
                'snippet' => $highlight ? (string)($row['snippet'] ?? '') : null,
            ];
        }

        return $results;
    }

    /**
     * Get total document count
     */
    public function getDocumentCount(): int
    {
        $result = $this->db->query('SELECT COUNT(*) as count FROM documents_meta');
        if (!$result instanceof SQLite3Result) {
            return 0;
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (!is_array($row)) {
            return 0;
        }

        return (int)($row['count'] ?? 0);
    }

    /**
     * Get document count by source
     *
     * @return array<string, int>
     */
    public function getDocumentCountBySource(): array
    {
        $result = $this->db->query('
            SELECT source, COUNT(*) as count
            FROM documents_meta
            GROUP BY source
        ');

        if (!$result instanceof SQLite3Result) {
            return [];
        }

        $counts = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (is_array($row) && isset($row['source'])) {
                $counts[(string)$row['source']] = (int)$row['count'];
            }
        }

        return $counts;
    }

    /**
     * Get document by ID
     *
     * @param string $docId Document ID
     * @return array<string, mixed>|null Document data or null if not found
     */
    public function getDocumentById(string $docId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                meta.doc_id,
                meta.source,
                meta.path,
                meta.title,
                meta.original_content,
                meta.metadata
            FROM documents_meta meta
            WHERE meta.doc_id = :doc_id
        ");

        if ($stmt === false) {
            return null;
        }

        $stmt->bindValue(':doc_id', $docId, SQLITE3_TEXT);
        $result = $stmt->execute();

        if (!$result instanceof SQLite3Result) {
            return null;
        }

        $row = $result->fetchArray(SQLITE3_ASSOC);

        if ($row === false) {
            return null;
        }

        // Return original markdown content (not cleaned content)
        $metadataArray = json_decode((string)$row['metadata'], true);

        return [
            'id' => (string)$row['doc_id'],
            'source' => (string)$row['source'],
            'path' => (string)$row['path'],
            'title' => (string)$row['title'],
            'content' => (string)($row['original_content'] ?? ''),
            'metadata' => is_array($metadataArray) ? $metadataArray : [],
        ];
    }

    /**
     * Optimize the search index
     */
    public function optimize(): void
    {
        $this->db->exec("INSERT INTO documents_fts(documents_fts) VALUES('optimize')");
    }

    /**
     * Destroy the search engine and delete the database file
     *
     * Closes the database connection and removes the database file from disk.
     * This is useful for cleaning up test databases or resetting the search index.
     *
     * @return bool True if the database file was deleted, false otherwise
     */
    public function destroy(): bool
    {
        // Close the database connection
        $this->db->close();

        // Delete the database file if it exists
        if (file_exists($this->databasePath)) {
            return @unlink($this->databasePath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        return false;
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
