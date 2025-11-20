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
    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse search';
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

        $service = new DocumentSearchService();

        try {
            // Check if index has documents
            $stats = $service->getStatistics();
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

            $results = $service->search($query, $options);

            if ($results === []) {
                $io->warning('No results found.');

                return static::CODE_SUCCESS;
            }

            $io->success(sprintf('Found %d result(s):', count($results)));
            $io->out('');

            // Prepare table data with headers as first row
            $tableData = [];

            // Add headers as first row
            if ($detailed) {
                $tableData[] = ['#', 'Title', 'Source', 'Path', 'Score'];
            } else {
                $tableData[] = ['#', 'Title', 'Source', 'Path'];
            }

            $snippets = [];
            foreach ($results as $i => $result) {
                $rank = $i + 1;
                $title = $result['title'] ?? 'Untitled';
                $relativePath = $result['path'] ?? '';
                $resultSource = $result['source'] ?? '';
                $snippet = $result['snippet'] ?? '';
                $rankScore = $result['score'] ?? 0;

                if ($detailed) {
                    $row = [
                        $rank,
                        $title,
                        $resultSource,
                        $relativePath,
                        sprintf('%.2f', $rankScore),
                    ];
                } else {
                    $row = [
                        $rank,
                        $title,
                        $resultSource,
                        $relativePath,
                    ];
                }

                $tableData[] = $row;

                // Store snippet for display after table
                if (!$noSnippet && $snippet !== '') {
                    $snippets[$rank] = ['title' => $title, 'snippet' => $snippet];
                }
            }

            // Display results table
            $io->helper('Table')->output($tableData);

            // Display snippets if enabled
            if (!$noSnippet && $snippets !== []) {
                $io->out('');
                $io->out('<info>Snippets:</info>');
                $io->out('');

                foreach ($snippets as $rank => $data) {
                    $io->out(sprintf('<info>%d.</info> <question>%s</question>', $rank, $data['title']));

                    // Clean up HTML markers and format snippet
                    $cleanSnippet = str_replace(['<mark>', '</mark>'], ['<warning>', '</warning>'], $data['snippet']);
                    $io->out('   ' . $cleanSnippet);
                    $io->out('');
                }
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
}
