<?php
declare(strict_types=1);

namespace Synapse\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;
use Synapse\Documentation\DocumentSearchService;

/**
 * Search Documentation Command
 *
 * Search indexed CakePHP documentation from the command line.
 */
class SearchDocsCommand extends Command
{
    private DocumentSearchService $service;

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse search';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Search CakePHP documentation';
    }

    /**
     * Configure command options
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Search CakePHP documentation')
            ->addArgument('query', [
                'help' => 'Search query',
                'required' => true,
            ])
            ->addOption('limit', [
                'short' => 'l',
                'help' => 'Maximum number of results to return',
                'default' => '10',
            ])
            ->addOption('fuzzy', [
                'short' => 'f',
                'help' => 'Enable fuzzy/prefix matching for typo tolerance',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('source', [
                'short' => 's',
                'help' => 'Filter results by source (e.g., cakephp-5x)',
                'default' => null,
            ])
            ->addOption('no-snippet', [
                'help' => 'Hide snippets in results',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('detailed', [
                'short' => 'd',
                'help' => 'Show detailed output',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('interactive', [
                'short' => 'i',
                'help' => 'Enable interactive mode for browsing results',
                'boolean' => true,
                'default' => true,
            ])
            ->addOption('non-interactive', [
                'help' => 'Disable interactive mode (for CI/scripts)',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * Execute command
     *
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @return int|null Exit code
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $query = $args->getArgument('query');
        if (!is_string($query) || trim($query) === '') {
            $io->error('Search query cannot be empty');

            return static::CODE_ERROR;
        }

        $limit = (int)$args->getOption('limit');
        $fuzzy = (bool)$args->getOption('fuzzy');
        $source = $args->getOption('source');
        $source = is_string($source) ? $source : null;

        $noSnippet = (bool)$args->getOption('no-snippet');
        $detailed = (bool)$args->getOption('detailed');
        $interactive = (bool)$args->getOption('interactive') && !(bool)$args->getOption('non-interactive');

        $this->service = new DocumentSearchService();

        try {
            // Check if index has documents
            $stats = $this->service->getStatistics();
            if ($stats['total_documents'] === 0) {
                $io->warning('Documentation index is empty. Run "bin/cake synapse index" first.');

                return static::CODE_ERROR;
            }

            $io->out(sprintf('<info>Searching for:</info> %s', $query));
            if ($fuzzy) {
                $io->out('<comment>Fuzzy matching enabled</comment>');
            }

            if ($source !== null) {
                $io->out(sprintf('<comment>Filtering by source: %s</comment>', $source));
            }

            $io->hr();

            // Perform search
            $options = [
                'limit' => $limit,
                'highlight' => true,
            ];

            if ($fuzzy) {
                $options['fuzzy'] = true;
            }

            if ($source !== null) {
                $options['sources'] = [$source];
            }

            $results = $this->service->search($query, $options);

            if ($results === []) {
                $io->warning('No results found.');

                return static::CODE_SUCCESS;
            }

            $io->success(sprintf('Found %d result(s):', count($results)));
            $io->out('');

            // Display results
            $this->displayResults($results, $noSnippet, $io);

            // Enter interactive mode if enabled
            if ($interactive) {
                return $this->interactiveMode($results, $io);
            }

            return static::CODE_SUCCESS;
        } catch (Exception $exception) {
            $io->error('Search failed: ' . $exception->getMessage());
            if ($detailed) {
                $io->out($exception->getTraceAsString());
            }

            return static::CODE_ERROR;
        }
    }

    /**
     * Display search results in a table format
     *
     * @param array<array<string, mixed>> $results Search results
     * @param bool $noSnippet Hide snippets
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function displayResults(array $results, bool $noSnippet, ConsoleIo $io): void
    {
        // Prepare table data with headers as first row
        $tableData = [];

        // Add headers as first row
        $tableData[] = ['#', 'Title', 'Source', 'Path', 'Score'];

        $snippets = [];
        foreach ($results as $i => $result) {
            $rank = $i + 1;
            $title = $result['title'] ?? 'Untitled';
            $relativePath = $result['path'] ?? '';
            $resultSource = $result['source'] ?? '';
            $snippet = $result['snippet'] ?? '';
            $rankScore = $result['score'] ?? 0;

            $row = [
                $rank,
                $title,
                $resultSource,
                $relativePath,
                sprintf('%.2f', $rankScore),
            ];

            $tableData[] = $row;

            // Store snippet for display after table
            if (!$noSnippet && $snippet !== '') {
                $snippets[$rank] = ['title' => $title, 'snippet' => $snippet, 'score' => $rankScore];
            }
        }

        // Display results table
        $io->helper('Table')->output($tableData);

        // Display snippets if enabled (in reverse order so best result is last/most visible)
        if (!$noSnippet && $snippets !== []) {
            $io->out('');
            $io->out('<info>Snippets:</info>');
            $io->out('');

            foreach (array_reverse($snippets, true) as $rank => $data) {
                $io->out(sprintf(
                    '<info>[%d]</info> <question>%s</question> <comment>(Score: %.2f)</comment>',
                    $rank,
                    $data['title'],
                    $data['score'],
                ));
                $io->hr();

                // Clean up HTML markers and format snippet
                $cleanSnippet = str_replace(['<mark>', '</mark>'], ['<warning>', '</warning>'], $data['snippet']);
                $io->out('   ' . $cleanSnippet);
                $io->out('');
            }
        }
    }

    /**
     * Enter interactive mode for browsing results
     *
     * @param array<array<string, mixed>> $results Search results
     * @param \Cake\Console\ConsoleIo $io Console I/O
     * @return int Exit code
     */
    private function interactiveMode(array $results, ConsoleIo $io): int
    {
        $io->out('');
        $io->hr();
        $io->out('<info>Interactive Mode</info>');
        $io->out('');

        while (true) {
            $io->out('Commands:');
            $io->out('  [1-' . count($results) . '] - View result details');
            $io->out('  [a]ll - Show all snippets');
            $io->out('  [q]uit - Exit');
            $io->out('');

            $choice = $io->ask('Enter your choice:');
            $choice = trim($choice);

            if (strtolower($choice) === 'q') {
                $io->out('<info>Exiting...</info>');

                return static::CODE_SUCCESS;
            }

            // Handle "all" command
            if (strtolower($choice) === 'a') {
                $this->showAllSnippets($results, $io);
                continue;
            }

            // Handle numeric selection
            if (is_numeric($choice)) {
                $index = (int)$choice - 1;
                if (isset($results[$index])) {
                    $this->viewResultDetail($results, $index, $io);
                } else {
                    $io->error('Invalid result number. Please enter a number between 1 and ' . count($results));
                }

                continue;
            }

            $io->error('Invalid choice. Please try again.');
        }
    }

    /**
     * Show all snippets
     *
     * @param array<array<string, mixed>> $results Search results
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function showAllSnippets(array $results, ConsoleIo $io): void
    {
        $io->out('');
        $io->hr();
        $io->out('<info>All Snippets:</info>');
        $io->out('');

        foreach (array_reverse($results, true) as $i => $result) {
            $rank = $i + 1;
            $title = $result['title'] ?? 'Untitled';
            $snippet = $result['snippet'] ?? '';
            $score = $result['score'] ?? 0;

            $io->out(sprintf(
                '<info>[%d]</info> <question>%s</question> <comment>(Score: %.2f)</comment>',
                $rank,
                $title,
                $score,
            ));
            $io->hr();

            if ($snippet !== '') {
                $cleanSnippet = str_replace(['<mark>', '</mark>'], ['<warning>', '</warning>'], $snippet);
                $io->out('   ' . $cleanSnippet);
            } else {
                $io->out('   <comment>No snippet available</comment>');
            }

            $io->out('');
        }

        $io->hr();
    }

    /**
     * View detailed information about a specific result
     *
     * @param array<array<string, mixed>> $results Search results
     * @param int $index Result index
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function viewResultDetail(array $results, int $index, ConsoleIo $io): void
    {
        $result = $results[$index];
        $totalResults = count($results);

        while (true) {
            $io->out('');
            $io->hr();

            // Display result header
            $rank = $index + 1;
            $io->out(sprintf('<info>Result %d of %d</info>', $rank, $totalResults));
            $io->out('');

            $title = $result['title'] ?? 'Untitled';
            $path = $result['path'] ?? '';
            $source = $result['source'] ?? '';
            $snippet = $result['snippet'] ?? '';
            $score = $result['score'] ?? 0;

            // Display metadata in table format
            $metadataTable = [
                ['Field', 'Value'],
                ['Title', $title],
                ['Source', $source],
                ['Path', $path],
                ['Score', sprintf('%.2f', $score)],
            ];
            $io->helper('Table')->output($metadataTable);
            $io->out('');

            // Display snippet
            if ($snippet !== '') {
                $io->out('<info>Snippet:</info>');
                $cleanSnippet = str_replace(['<mark>', '</mark>'], ['<warning>', '</warning>'], $snippet);
                $io->out($cleanSnippet);
                $io->out('');
            }

            $io->hr();

            // Show navigation options
            $io->out('Commands:');
            $io->out('  [v]iew - View full document content');
            if ($index > 0) {
                $io->out('  [p]revious - View previous result');
            }

            if ($index < $totalResults - 1) {
                $io->out('  [n]ext - View next result');
            }

            $io->out('  [b]ack - Back to result list');
            $io->out('  [q]uit - Exit');
            $io->out('');

            $action = $io->ask('Enter your choice:');
            $action = strtolower(trim($action));

            if ($action === 'q') {
                $io->out('<info>Exiting...</info>');
                exit(static::CODE_SUCCESS);
            }

            switch ($action) {
                case 'v':
                    $this->viewFullDocument($result, $io);
                    break;

                case 'p':
                    if ($index > 0) {
                        $index--;
                        $result = $results[$index];
                    } else {
                        $io->error('Already at first result');
                    }

                    break;

                case 'n':
                    if ($index < $totalResults - 1) {
                        $index++;
                        $result = $results[$index];
                    } else {
                        $io->error('Already at last result');
                    }

                    break;

                case 'b':
                    return;

                default:
                    $io->error('Invalid choice. Please try again.');
            }
        }
    }

    /**
     * View full document content
     *
     * Displays the original markdown content with full formatting preserved
     * (code blocks, links, lists, etc.), not the cleaned content used for search.
     *
     * @param array<string, mixed> $result Search result
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function viewFullDocument(array $result, ConsoleIo $io): void
    {
        $documentId = $result['id'] ?? null;

        if ($documentId === null) {
            $io->error('Document ID not available');

            return;
        }

        try {
            // Get document with original markdown content
            $document = $this->service->getSearchEngine()->getDocumentById($documentId);

            if ($document === null) {
                $io->error('Document not found');

                return;
            }

            $io->out('');
            $io->hr();
            $io->out('<info>Full Document Content:</info>');
            $io->out('');

            // Display metadata in table format
            $documentMetadataTable = [
                ['Field', 'Value'],
                ['Title', $document['title'] ?? 'Untitled'],
                ['Source', $document['source'] ?? ''],
                ['Path', $document['path'] ?? ''],
            ];
            $io->helper('Table')->output($documentMetadataTable);
            $io->out('');
            $io->hr();
            $io->out('');

            // Display content
            $content = $document['content'] ?? '';
            if ($content !== '') {
                // Split content into lines for better readability
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $io->out($line);
                }
            } else {
                $io->out('<comment>No content available</comment>');
            }

            $io->out('');
            $io->hr();
            $io->out('');
            $io->out('Press <info>Enter</info> to continue...');
            $io->ask('');
        } catch (Exception $exception) {
            $io->error('Failed to load document: ' . $exception->getMessage());
        }
    }
}
