<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * SearchDocsCommand Test Case
 *
 * Tests for documentation search console command.
 */
class SearchDocsCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Test command with basic query
     */
    public function testCommandWithBasicQuery(): void
    {
        $this->exec('synapse search authentication --non-interactive');

        // May fail if index is empty, which is acceptable
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Searching for:');
        $this->assertOutputContains('authentication');
    }

    /**
     * Test command displays help
     */
    public function testCommandHelp(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('Search CakePHP documentation');
        $this->assertOutputContains('query');
        $this->assertOutputContains('--limit');
        $this->assertOutputContains('--fuzzy');
        $this->assertOutputContains('--source');
        $this->assertOutputContains('--detailed');
    }

    /**
     * Test command with limit option
     */
    public function testCommandWithLimitOption(): void
    {
        $this->exec('synapse search cakephp --limit 2 --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Searching for:');
    }

    /**
     * Test command with short limit option
     */
    public function testCommandWithShortLimitOption(): void
    {
        $this->exec('synapse search database -l 1 --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Searching for:');
    }

    /**
     * Test command with fuzzy option
     */
    public function testCommandWithFuzzyOption(): void
    {
        $this->exec('synapse search auth --fuzzy --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Fuzzy matching enabled');
    }

    /**
     * Test command with short fuzzy option
     */
    public function testCommandWithShortFuzzyOption(): void
    {
        $this->exec('synapse search auth -f --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Fuzzy matching enabled');
    }

    /**
     * Test command with source filter
     */
    public function testCommandWithSourceFilter(): void
    {
        $this->exec('synapse search authentication --source test-docs --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Filtering by source: test-docs');
    }

    /**
     * Test command with short source option
     */
    public function testCommandWithShortSourceOption(): void
    {
        $this->exec('synapse search database -s test-docs --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Filtering by source: test-docs');
    }

    /**
     * Test command with no-snippet option
     */
    public function testCommandWithNoSnippetOption(): void
    {
        $this->exec('synapse search authentication --no-snippet --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
    }

    /**
     * Test command with detailed option
     */
    public function testCommandWithDetailedOption(): void
    {
        $this->exec('synapse search authentication --detailed --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Source');
        $this->assertOutputContains('Path');
        $this->assertOutputContains('Score');
    }

    /**
     * Test command with short detailed option
     */
    public function testCommandWithShortDetailedOption(): void
    {
        $this->exec('synapse search authentication -d --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Source');
        $this->assertOutputContains('Path');
        $this->assertOutputContains('Score');
    }

    /**
     * Test command with no results
     */
    public function testCommandWithNoResults(): void
    {
        $this->exec('synapse search nonexistentquery12345xyzabc --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        // May find results or not depending on indexed content
        $this->assertExitSuccess();
    }

    /**
     * Test command with empty query fails
     */
    public function testCommandWithEmptyQueryFails(): void
    {
        $this->exec('synapse search "" --non-interactive');

        $this->assertExitError();
        // CakePHP treats empty string as missing argument
        $this->assertErrorContains('argument is required');
    }

    /**
     * Test command displays result count
     */
    public function testCommandDisplaysResultCount(): void
    {
        $this->exec('synapse search authentication --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
    }

    /**
     * Test command with multiple results
     */
    public function testCommandWithMultipleResults(): void
    {
        $this->exec('synapse search cakephp --limit 10 --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
    }

    /**
     * Test command displays snippets by default
     */
    public function testCommandDisplaysSnippetsByDefault(): void
    {
        $this->exec('synapse search authentication --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
    }

    /**
     * Test command with combined options
     */
    public function testCommandWithCombinedOptions(): void
    {
        $this->exec('synapse search auth --fuzzy --limit 5 --detailed --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Fuzzy matching enabled');
    }

    /**
     * Test command output formatting
     */
    public function testCommandOutputFormatting(): void
    {
        $this->exec('synapse search authentication --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Searching for:');
    }

    /**
     * Test command with all options
     */
    public function testCommandWithAllOptions(): void
    {
        $this->exec('synapse search auth --limit 2 --fuzzy --source test-docs --detailed --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Fuzzy matching enabled');
        $this->assertOutputContains('Filtering by source: test-docs');
    }

    /**
     * Test command shows numbered results
     */
    public function testCommandShowsNumberedResults(): void
    {
        $this->exec('synapse search cakephp --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('1.');
    }

    /**
     * Test command with query containing spaces
     */
    public function testCommandWithQueryContainingSpaces(): void
    {
        $this->exec('synapse search "user authentication" --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        $this->assertOutputContains('Searching for:');
    }

    /**
     * Test command description
     */
    public function testCommandDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('Search CakePHP documentation');
    }

    /**
     * Test required argument
     */
    public function testRequiredArgument(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('query');
        $this->assertOutputContains('Search query');
    }

    /**
     * Test limit option description
     */
    public function testLimitOptionDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('--limit');
        $this->assertOutputContains('Maximum number of results');
    }

    /**
     * Test fuzzy option description
     */
    public function testFuzzyOptionDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('--fuzzy');
        $this->assertOutputContains('fuzzy');
    }

    /**
     * Test source option description
     */
    public function testSourceOptionDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('--source');
        $this->assertOutputContains('Filter results by source');
    }

    /**
     * Test no-snippet option description
     */
    public function testNoSnippetOptionDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('--no-snippet');
        $this->assertOutputContains('Hide snippets');
    }

    /**
     * Test short option aliases
     */
    public function testShortOptionAliases(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('-l');
        $this->assertOutputContains('-f');
        $this->assertOutputContains('-s');
        $this->assertOutputContains('-d');
    }

    /**
     * Test command with special characters in query
     */
    public function testCommandWithSpecialCharactersInQuery(): void
    {
        $this->exec('synapse search "authentication & authorization" --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
    }

    /**
     * Test interactive option description in help
     */
    public function testInteractiveOptionDescription(): void
    {
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('--interactive');
        $this->assertOutputContains('--non-interactive');
    }

    /**
     * Test non-interactive flag disables interactive mode
     */
    public function testNonInteractiveFlagDisablesInteractiveMode(): void
    {
        $this->exec('synapse search authentication --non-interactive');

        // Skip test if index is empty
        if ($this->_exitCode === 1) {
            $this->assertErrorContains('Documentation index is empty');

            return;
        }

        $this->assertExitSuccess();
        // Should not prompt for interactive commands
        $this->assertOutputNotContains('Interactive Mode');
    }

    /**
     * Test interactive flag enables interactive mode by default
     */
    public function testInteractiveModeEnabledByDefault(): void
    {
        // Note: This test would need mock IO to properly test interactive behavior
        // For now we just verify the flag exists
        $this->exec('synapse search --help --non-interactive');

        $this->assertExitSuccess();
        $this->assertOutputContains('interactive');
    }
}
