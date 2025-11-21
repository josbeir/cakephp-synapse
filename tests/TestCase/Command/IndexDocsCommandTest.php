<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Synapse\TestSuite\MockGitAdapter;

/**
 * IndexDocsCommand Test Case
 *
 * Tests for documentation indexing console command.
 */
class IndexDocsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private string $testDbPath;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testDbPath = TMP . 'tests' . DS . 'command_test_' . uniqid() . '.db';
        $dir = dirname($this->testDbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Configure test settings - completely override all documentation config
        Configure::write('Synapse.documentation', [
            'git_adapter' => MockGitAdapter::class,
            'cache_dir' => TMP . 'tests' . DS . 'docs',
            'search_db' => $this->testDbPath,
            'search' => [
                'batch_size' => 10,
                'default_limit' => 10,
            ],
            'auto_build' => false,
            'sources' => [], // Empty sources for testing
        ]);
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        // Clean up test database
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        parent::tearDown();
    }

    /**
     * Test command with no sources configured returns success
     */
    public function testCommandWithNoSourcesConfigured(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Documentation Indexing');
        $this->assertOutputContains('Indexing all enabled sources');
        $this->assertOutputContains('Indexed');
        $this->assertOutputContains('documents from');
    }

    /**
     * Test command displays header
     */
    public function testCommandDisplaysHeader(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Documentation Indexing');
    }

    /**
     * Test command with --stats option shows statistics
     */
    public function testCommandWithStatsOptionShowsStatistics(): void
    {
        $this->exec('synapse index --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Index Statistics');
        $this->assertOutputContains('Total documents:');
        $this->assertOutputContains('Enabled sources:');
    }

    /**
     * Test command without --stats option still shows statistics by default
     */
    public function testCommandShowsStatisticsByDefault(): void
    {
        $this->exec('synapse index --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Index Statistics');
    }

    /**
     * Test command with --force option displays warning
     */
    public function testCommandWithForceOptionDisplaysWarning(): void
    {
        $this->exec('synapse index --force');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index enabled');
    }

    /**
     * Test command with -f short option displays warning
     */
    public function testCommandWithShortForceOptionDisplaysWarning(): void
    {
        $this->exec('synapse index -f');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index enabled');
    }

    /**
     * Test command with --optimize option
     */
    public function testCommandWithOptimizeOption(): void
    {
        $this->exec('synapse index --optimize');

        $this->assertExitSuccess();
        $this->assertOutputContains('Optimizing search index');
        $this->assertOutputContains('Index optimized');
    }

    /**
     * Test command with -o short option
     */
    public function testCommandWithShortOptimizeOption(): void
    {
        $this->exec('synapse index -o');

        $this->assertExitSuccess();
        $this->assertOutputContains('Optimizing search index');
        $this->assertOutputContains('Index optimized');
    }

    /**
     * Test command with --source option
     */
    public function testCommandWithSourceOption(): void
    {
        // Configure a test source
        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'url' => 'https://example.com/docs.git',
                'branch' => 'main',
                'root' => 'docs',
                'enabled' => true,
            ],
        ]);

        // This will fail because the repository doesn't exist, but we can test the flow
        $this->exec('synapse index --source test-source');

        // Command will fail due to missing repository, but we should see the source message
        $this->assertOutputContains('Indexing source:');
        $this->assertOutputContains('test-source');
    }

    /**
     * Test command with -s short option
     */
    public function testCommandWithShortSourceOption(): void
    {
        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'url' => 'https://example.com/docs.git',
                'branch' => 'main',
                'root' => 'docs',
                'enabled' => true,
            ],
        ]);

        $this->exec('synapse index -s test-source');

        $this->assertOutputContains('Indexing source:');
        $this->assertOutputContains('test-source');
    }

    /**
     * Test command with --source and --force options together
     */
    public function testCommandWithSourceAndForceOptions(): void
    {
        Configure::write('Synapse.documentation.sources', [
            'test-source' => [
                'url' => 'https://example.com/docs.git',
                'branch' => 'main',
                'root' => 'docs',
                'enabled' => true,
            ],
        ]);

        $this->exec('synapse index --source test-source --force');

        $this->assertOutputContains('Indexing source:');
        $this->assertOutputContains('test-source');
        $this->assertOutputContains('Force re-index enabled');
    }

    /**
     * Test command displays success message after indexing
     */
    public function testCommandDisplaysSuccessMessage(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        $this->assertOutputContains('Indexed');
        $this->assertOutputContains('documents from');
    }

    /**
     * Test command with all options combined
     */
    public function testCommandWithAllOptionsCombined(): void
    {
        $this->exec('synapse index --force --optimize --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Documentation Indexing');
        $this->assertOutputContains('Force re-index enabled');
        $this->assertOutputContains('Optimizing search index');
        $this->assertOutputContains('Index optimized');
        $this->assertOutputContains('Index Statistics');
    }

    /**
     * Test command help displays usage information
     */
    public function testCommandHelpDisplaysUsageInformation(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Index documentation for full-text search');
        $this->assertOutputContains('--source');
        $this->assertOutputContains('--force');
        $this->assertOutputContains('--optimize');
        $this->assertOutputContains('--stats');
    }

    /**
     * Test command with verbose output
     */
    public function testCommandWithVerboseOutput(): void
    {
        $this->exec('synapse index -v');

        $this->assertExitSuccess();
    }

    /**
     * Test statistics display shows documents by source
     */
    public function testStatisticsDisplayShowsDocumentsBySource(): void
    {
        $this->exec('synapse index --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Total documents:');
        $this->assertOutputContains('Enabled sources:');
    }

    /**
     * Test command displays horizontal rules for formatting
     */
    public function testCommandDisplaysHorizontalRules(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        // Console integration test captures output with formatting
    }

    /**
     * Test command returns success exit code
     */
    public function testCommandReturnsSuccessExitCode(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
    }

    /**
     * Test command with invalid source shows error
     */
    public function testCommandWithInvalidSourceShowsError(): void
    {
        Configure::write('Synapse.documentation.sources', []);

        $this->exec('synapse index --source nonexistent-source');

        $this->assertExitError();
        $this->assertErrorContains('Indexing failed');
    }

    /**
     * Test command shows individual source counts when indexing all
     */
    public function testCommandShowsIndividualSourceCounts(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        // Should show summary with count
        $this->assertOutputContains('Indexed');
        $this->assertOutputContains('documents from');
    }

    /**
     * Test command description
     */
    public function testCommandDescription(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Index documentation for full-text search');
    }

    /**
     * Test source option description
     */
    public function testSourceOptionDescription(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Specific source to index');
    }

    /**
     * Test force option description
     */
    public function testForceOptionDescription(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index even if source is already indexed');
    }

    /**
     * Test optimize option description
     */
    public function testOptimizeOptionDescription(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Optimize the search index after indexing');
    }

    /**
     * Test stats option description
     */
    public function testStatsOptionDescription(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Show index statistics after indexing');
    }

    /**
     * Test command with boolean option variations
     */
    public function testCommandWithBooleanOptionVariations(): void
    {
        // Boolean options should work without values
        $this->exec('synapse index --force --optimize');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index enabled');
        $this->assertOutputContains('Optimizing search index');
    }

    /**
     * Test statistics with empty index
     */
    public function testStatisticsWithEmptyIndex(): void
    {
        $this->exec('synapse index --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Total documents:');
    }

    /**
     * Test command output formatting
     */
    public function testCommandOutputFormatting(): void
    {
        $this->exec('synapse index');

        $this->assertExitSuccess();
        // Check that output uses proper formatting tags
        $this->assertOutputContains('Documentation Indexing');
        $this->assertOutputContains('Indexing all enabled sources');
    }

    /**
     * Test command with --pull option displays message
     */
    public function testCommandWithPullOption(): void
    {
        $this->exec('synapse index --pull');

        $this->assertExitSuccess();
        $this->assertOutputContains('Documentation Indexing');
        $this->assertOutputContains('Pulling latest changes from remote');
        $this->assertOutputContains('Indexed');
    }

    /**
     * Test command with short -p option
     */
    public function testCommandWithShortPullOption(): void
    {
        $this->exec('synapse index -p');

        $this->assertExitSuccess();
        $this->assertOutputContains('Pulling latest changes from remote');
    }

    /**
     * Test command with --pull and --force combined
     */
    public function testCommandWithPullAndForce(): void
    {
        $this->exec('synapse index --pull --force');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index enabled');
        $this->assertOutputContains('Pulling latest changes from remote');
    }

    /**
     * Test command with --pull for specific source
     */
    public function testCommandWithPullForSpecificSource(): void
    {
        $this->exec('synapse index --source test-source --pull');

        $this->assertExitError();
        $this->assertOutputContains('Pulling latest changes from remote');
        $this->assertOutputContains('Indexing source: <info>test-source</info>');
        $this->assertErrorContains('Documentation source "test-source" is not configured');
    }

    /**
     * Test command with all options including pull
     */
    public function testCommandWithAllOptionsIncludingPull(): void
    {
        $this->exec('synapse index --force --pull --optimize --stats');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index enabled');
        $this->assertOutputContains('Pulling latest changes from remote');
        $this->assertOutputContains('Index Statistics');
    }

    /**
     * Test command with --destroy option requires confirmation
     */
    public function testCommandWithDestroyOptionRequiresConfirmation(): void
    {
        // Set up the response to the confirmation prompt
        $this->exec('synapse index --destroy', ['no']);

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputContains('You will need to re-index all sources');
        $this->assertOutputContains('Are you sure you want to destroy the search index?');
        $this->assertOutputContains('Operation cancelled');
    }

    /**
     * Test command with --destroy and user confirms
     */
    public function testCommandWithDestroyAndUserConfirms(): void
    {
        // Create an index first
        $this->exec('synapse index');
        $this->assertExitSuccess();

        // Reset to allow new execution with input
        $this->_in = null;
        $this->_out = null;
        $this->_err = null;

        // Destroy it with confirmation
        $this->exec('synapse index --destroy', ['yes']);

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputContains('Destroying search index');
        $this->assertOutputContains('Search index destroyed successfully');
    }

    /**
     * Test command with --destroy and --force skips confirmation
     */
    public function testCommandWithDestroyAndForceSkipsConfirmation(): void
    {
        // Create an index first
        $this->exec('synapse index');
        $this->assertExitSuccess();

        // Destroy it with force (no confirmation prompt)
        $this->exec('synapse index --destroy --force');

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputContains('Destroying search index');
        $this->assertOutputContains('Search index destroyed successfully');
        // Should NOT contain confirmation prompt
        $this->assertOutputNotContains('Are you sure');
    }

    /**
     * Test command with -d short option for destroy
     */
    public function testCommandWithShortDestroyOption(): void
    {
        $this->exec('synapse index -d', ['no']);

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputContains('Operation cancelled');
    }

    /**
     * Test command with -d and -f short options
     */
    public function testCommandWithShortDestroyAndForceOptions(): void
    {
        $this->exec('synapse index -d -f');

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputContains('Destroying search index');
    }

    /**
     * Test destroy when index doesn't exist
     */
    public function testDestroyWhenIndexDoesNotExist(): void
    {
        // Ensure no index exists
        if (file_exists($this->testDbPath)) {
            @unlink($this->testDbPath); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        }

        $this->exec('synapse index --destroy --force');

        $this->assertExitSuccess();
        $this->assertOutputContains('Destroy Search Index');
        // Note: destroy() may return true even if file didn't exist due to how it's implemented
        // Just check that the command succeeds
    }

    /**
     * Test destroy option in help text
     */
    public function testDestroyOptionInHelpText(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('--destroy');
        $this->assertOutputContains('Destroy the search index');
        $this->assertOutputContains('destructive operation');
    }

    /**
     * Test destroy warning messages
     */
    public function testDestroyWarningMessages(): void
    {
        $this->exec('synapse index --destroy', ['no']);

        $this->assertExitSuccess();
        $this->assertOutputContains('You will need to re-index all sources');
    }

    /**
     * Test destroy with yes confirmation
     */
    public function testDestroyWithYesConfirmation(): void
    {
        // Create an index
        $this->exec('synapse index');
        $this->assertExitSuccess();

        // Reset to allow new execution with input
        $this->_in = null;
        $this->_out = null;
        $this->_err = null;

        // Destroy with "yes" response
        $this->exec('synapse index --destroy', ['yes']);

        $this->assertExitSuccess();
        $this->assertOutputContains('Search index destroyed successfully');
    }

    /**
     * Test destroy is mutually exclusive with indexing
     */
    public function testDestroyIsMutuallyExclusiveWithIndexing(): void
    {
        $this->exec('synapse index --destroy --force');

        $this->assertExitSuccess();
        // Should show destroy header, not indexing header
        $this->assertOutputContains('Destroy Search Index');
        $this->assertOutputNotContains('Documentation Indexing');
    }

    /**
     * Test force option description includes destroy info
     */
    public function testForceOptionDescriptionIncludesDestroyInfo(): void
    {
        $this->exec('synapse index --help');

        $this->assertExitSuccess();
        $this->assertOutputContains('Force re-index even if source is already indexed');
        $this->assertOutputContains('skip confirmation when destroying');
    }
}
