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

            foreach ($results as $i => $result) {
                $rank = $i + 1;
                $title = $result['title'] ?? 'Untitled';
                $relativePath = $result['path'] ?? '';
                $absolutePath = $result['absolute_path'] ?? '';
                $resultSource = $result['source'] ?? '';
                $snippet = $result['snippet'] ?? '';
                $rankScore = $result['rank'] ?? 0;

                // Display result header
                $io->out(sprintf(
                    '<info>%d.</info> <question>%s</question>',
                    $rank,
                    $title,
                ));
                $io->hr();

                if ($detailed) {
                    $io->out(sprintf('   Source: %s', $resultSource));
                    $io->out(sprintf('   File: %s', $absolutePath));
                    if ($relativePath !== '') {
                        $io->out(sprintf('   Path: %s', $relativePath));
                    }

                    $io->out(sprintf('   Relevance: %.2f', $rankScore));
                } else {
                    $io->out(sprintf('   File: %s', $absolutePath));
                }

                // Display snippet if enabled
                if (!$noSnippet && $snippet !== '') {
                    $io->out('');
                    // Clean up HTML markers and format snippet
                    $cleanSnippet = str_replace(['<mark>', '</mark>'], ['<warning>', '</warning>'], $snippet);
                    $io->out('   ' . $cleanSnippet);
                }

                $io->out('');
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
