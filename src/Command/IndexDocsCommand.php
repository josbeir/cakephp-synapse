<?php
declare(strict_types=1);

namespace Synapse\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIoInterface;
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
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'synapse index';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Index documentation for full-text search';
    }

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
                'help' => 'Specific source to index (e.g., cakephp-6x). If not specified, indexes all enabled sources.',
                'default' => null,
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force re-index even if source is already indexed, or skip confirmation when destroying',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('pull', [
                'short' => 'p',
                'help' => 'Pull latest changes from remote before indexing',
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
            ])
            ->addOption('destroy', [
                'short' => 'd',
                'help' => 'Destroy the search index (destructive operation, requires confirmation unless --force)',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * Execute command
     *
     * @return int|null Exit code
     */
    public function execute(): ?int
    {
        $service = new DocumentSearchService();
        $destroy = (bool)$this->args->getOption('destroy');

        // Handle destroy operation separately
        if ($destroy) {
            return $this->handleDestroy($service, $this->args, $this->io);
        }

        $this->io->out('<info>Documentation Indexing</info>');
        $this->io->hr();

        $source = $this->args->getOption('source');
        $source = is_string($source) ? $source : null;

        $force = (bool)$this->args->getOption('force');
        $pull = (bool)$this->args->getOption('pull');
        $optimize = (bool)$this->args->getOption('optimize');
        $showStats = (bool)$this->args->getOption('stats');

        try {
            if ($source) {
                // Index specific source
                $this->io->out(sprintf('Indexing source: <info>%s</info>', $source));
                if ($force) {
                    $this->io->out('<warning>Force re-index enabled</warning>');
                }

                if ($pull) {
                    $this->io->out('<comment>Pulling latest changes from remote</comment>');
                }

                $count = $service->indexSource($source, $force, $pull);
                $this->io->success(sprintf('Indexed %d documents from source: %s', $count, $source));
            } else {
                // Index all enabled sources
                $this->io->out('Indexing all enabled sources...');
                if ($force) {
                    $this->io->out('<warning>Force re-index enabled</warning>');
                }

                if ($pull) {
                    $this->io->out('<comment>Pulling latest changes from remote</comment>');
                }

                $results = $service->indexAll($force, $pull);

                foreach ($results as $sourceKey => $count) {
                    $this->io->out(sprintf(
                        '  • <info>%s</info>: %d documents',
                        $sourceKey,
                        $count,
                    ));
                }

                $totalCount = array_sum($results);
                $this->io->success(sprintf('Indexed %d total documents from %d sources', $totalCount, count($results)));
            }

            // Optimize index if requested
            if ($optimize) {
                $this->io->out('Optimizing search index...');
                $service->optimize();
                $this->io->success('Index optimized');
            }

            // Show statistics if requested
            if ($showStats) {
                $this->io->hr();
                $this->displayStatistics($service, $this->io);
            }

            return static::CODE_SUCCESS;
        } catch (Exception $exception) {
            $this->io->error('Indexing failed: ' . $exception->getMessage());
            if ($this->io->level() >= ConsoleIoInterface::VERBOSE) {
                $this->io->out($exception->getTraceAsString());
            }

            return static::CODE_ERROR;
        }
    }

    /**
     * Display index statistics
     *
     * @param \Synapse\Documentation\DocumentSearchService $service Search service
     * @param \Cake\Console\ConsoleIoInterface $io Console I/O
     */
    private function displayStatistics(DocumentSearchService $service, ConsoleIoInterface $io): void
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

    /**
     * Handle index destruction
     *
     * @param \Synapse\Documentation\DocumentSearchService $service Search service
     * @param \Cake\Console\Arguments $args Command arguments
     * @param \Cake\Console\ConsoleIoInterface $io Console I/O
     * @return int Exit code
     */
    private function handleDestroy(DocumentSearchService $service, Arguments $args, ConsoleIoInterface $io): int
    {
        $io->out('<warning>Destroy Search Index</warning>');
        $io->hr();

        $force = (bool)$args->getOption('force');

        // Require confirmation unless --force is used
        if (!$force) {
            $io->warning('This will permanently delete the search index and all indexed documents.');
            $io->out('You will need to re-index all sources to use search functionality again.');
            $io->out('');

            $confirm = $io->askChoice('Are you sure you want to destroy the search index?', ['yes', 'no'], 'no');

            if ($confirm !== 'yes') {
                $io->out('<info>Operation cancelled</info>');

                return static::CODE_SUCCESS;
            }
        }

        try {
            $io->out('Destroying search index...');

            $destroyed = $service->destroy();

            if ($destroyed) {
                $io->success('Search index destroyed successfully');
            } else {
                $io->warning('Search index did not exist or was already destroyed');
            }

            return static::CODE_SUCCESS;
        } catch (Exception $exception) {
            $io->error('Failed to destroy search index: ' . $exception->getMessage());
            if ($io->level() >= ConsoleIoInterface::VERBOSE) {
                $io->out($exception->getTraceAsString());
            }

            return static::CODE_ERROR;
        }
    }
}
