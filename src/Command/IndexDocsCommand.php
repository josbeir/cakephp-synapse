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
 * Index Documentation Command
 *
 * Indexes documentation from configured sources for full-text search.
 */
class IndexDocsCommand extends Command
{
    /**
     * Configure command options
     *
     * @param \Cake\Console\ConsoleOptionParser $parser Option parser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Index documentation for full-text search')
            ->addOption('source', [
                'short' => 's',
                'help' => 'Specific source to index (e.g., cakephp-5x). If not specified, indexes all enabled sources.',
                'default' => null,
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force re-index even if source is already indexed',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('optimize', [
                'short' => 'o',
                'help' => 'Optimize the search index after indexing',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('stats', [
                'help' => 'Show index statistics after indexing',
                'boolean' => true,
                'default' => true,
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
        $io->out('<info>Documentation Indexing</info>');
        $io->hr();

        $service = new DocumentSearchService();
        $source = $args->getOption('source');
        $source = is_string($source) ? $source : null;

        $force = (bool)$args->getOption('force');
        $optimize = (bool)$args->getOption('optimize');
        $showStats = (bool)$args->getOption('stats');

        try {
            if ($source) {
                // Index specific source
                $io->out(sprintf('Indexing source: <info>%s</info>', $source));
                if ($force) {
                    $io->out('<warning>Force re-index enabled</warning>');
                }

                $count = $service->indexSource($source, $force);
                $io->success(sprintf('Indexed %d documents from source: %s', $count, $source));
            } else {
                // Index all enabled sources
                $io->out('Indexing all enabled sources...');
                if ($force) {
                    $io->out('<warning>Force re-index enabled</warning>');
                }

                $results = $service->indexAll($force);

                foreach ($results as $sourceKey => $count) {
                    $io->out(sprintf(
                        '  • <info>%s</info>: %d documents',
                        $sourceKey,
                        $count,
                    ));
                }

                $totalCount = array_sum($results);
                $io->success(sprintf('Indexed %d total documents from %d sources', $totalCount, count($results)));
            }

            // Optimize index if requested
            if ($optimize) {
                $io->out('Optimizing search index...');
                $service->optimize();
                $io->success('Index optimized');
            }

            // Show statistics if requested
            if ($showStats) {
                $io->hr();
                $this->displayStatistics($service, $io);
            }

            return static::CODE_SUCCESS;
        } catch (Exception $exception) {
            $io->error('Indexing failed: ' . $exception->getMessage());
            if ($io->level() >= ConsoleIo::VERBOSE) {
                $io->out($exception->getTraceAsString());
            }

            return static::CODE_ERROR;
        }
    }

    /**
     * Display index statistics
     *
     * @param \Synapse\Documentation\DocumentSearchService $service Search service
     * @param \Cake\Console\ConsoleIo $io Console I/O
     */
    private function displayStatistics(DocumentSearchService $service, ConsoleIo $io): void
    {
        $io->out('<info>Index Statistics</info>');

        $stats = $service->getStatistics();

        $io->out(sprintf('Total documents: <info>%d</info>', $stats['total_documents']));

        if (!empty($stats['documents_by_source'])) {
            $io->out('Documents by source:');
            foreach ($stats['documents_by_source'] as $source => $count) {
                $io->out(sprintf('  • %s: %d', $source, $count));
            }
        }

        $io->out(sprintf('Enabled sources: <info>%s</info>', implode(', ', $stats['sources'])));
    }
}
