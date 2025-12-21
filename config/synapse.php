<?php
declare(strict_types=1);

use Synapse\Documentation\Git\GitAdapter;
use Synapse\SynapsePlugin;

/**
 * Synapse Plugin Configuration
 *
 * Configuration for the MCP (Model Context Protocol) server plugin.
 */
return [
    'Synapse' => [
        /**
         * Server Information
         *
         * These values are sent to MCP clients during initialization.
         */
        'serverInfo' => [
            'name' => env('MCP_SERVER_NAME', 'Adaptic MCP Server'),
            'version' => env('MCP_SERVER_VERSION', SynapsePlugin::VERSION),
        ],

        /**
         * Logger Configuration
         *
         * Configure the logger engine name to use for MCP server logging.
         * Defaults to stderr to not clash with stdio output.
         */
        'logger' => null,

        /**
         * Protocol Version
         *
         * MCP protocol version to use. Available versions:
         * - '2024-11-05' (recommended, most compatible)
         * - '2025-03-26'
         * - '2025-06-18'
         * - '2025-11-25'
         */
        'protocolVersion' => env('MCP_PROTOCOL_VERSION', '2024-11-05'),

        /**
         * Discovery Configuration
         *
         * Configure which directories to scan for MCP elements (Tools, Resources, Prompts).
         * Elements are discovered using PHP 8 attributes like #[McpTool], #[McpResource], etc.
         */
        'discovery' => [
            // Directories to scan for MCP elements (relative to ROOT)
            // Note: The plugin's built-in tools are automatically included
            'scanDirs' => ['src'],

            // Directories to exclude from scanning
            'excludeDirs' => ['tests', 'vendor', 'tmp', 'logs', 'webroot'],

            /**
             * Discovery Cache
             *
             * Cache engine name to use for discovery caching (improves startup performance).
             * Use any cache configuration defined in config/app.php (e.g., 'default', 'mcp').
             * Defaults to 'default' cache engine if not specified.
             *
             * Examples:
             * - 'default' (use default cache engine - recommended)
             * - 'mcp' (use dedicated cache engine)
             * - Any other cache engine name from config/app.php
             *
             * Configure cache settings in config/app.php:
             * 'Cache' => [
             *     'mcp' => [
             *         'className' => 'File',
             *         'duration' => '+1 hour',
             *         'path' => CACHE . 'mcp' . DS,
             *     ],
             * ]
             */
            'cache' => env('MCP_DISCOVERY_CACHE', 'default'),
        ],

        /**
         * Documentation Search Configuration
         *
         * Configure fulltext search for documentation repositories.
         * Uses YetiSearch (SQLite FTS5) for indexing and searching.
         */
        'documentation' => [
            /**
             * Git Adapter
             *
             * Class name for git operations. Allows overriding for testing.
             * Defaults to Synapse\Documentation\Git\GitAdapter::class
             */
            'git_adapter' => GitAdapter::class,

            /**
             * Cache Directory
             *
             * Directory where cloned repositories and search databases are stored.
             * Defaults to TMP . 'synapse/docs'
             */
            'cache_dir' => env('MCP_DOCS_CACHE_DIR', TMP . 'synapse' . DS . 'docs'),

            /**
             * Search Database
             *
             * Path to the SQLite database used by YetiSearch.
             * Defaults to cache_dir/search.db
             */
            'search_db' => env('MCP_DOCS_SEARCH_DB', null), // null = cache_dir/search.db

            /**
             * Auto-build Index
             *
             * Whether to automatically build the index on first search if it doesn't exist.
             */
            'auto_build' => env('MCP_DOCS_AUTO_BUILD', true),

            /**
             * Documentation Sources
             *
             * Configure one or more documentation repositories to index.
             * Each source has a unique key and configuration.
             */
            'sources' => [
                'cakephp-5x' => [
                    'enabled' => true,
                    'repository' => 'https://github.com/cakephp/docs-md.git',
                    'branch' => '5.x',
                    'root' => 'docs/en', // Root directory within repo to index
                    'metadata' => [
                        'name' => 'CakePHP 5.x Documentation',
                        'version' => '5.x',
                        'language' => 'en',
                    ],
                ],
                // Additional sources can be added here
                // 'cakephp-4x' => [...],
            ],

            /**
             * Search Configuration
             *
             * Configure the search behavior.
             */
            'search' => [
                /**
                 * Batch Size
                 *
                 * Number of documents to index in a single batch.
                 * Higher values = faster indexing but more memory usage.
                 */
                'batch_size' => 100,

                /**
                 * Default Results Limit
                 *
                 * Default number of search results to return.
                 */
                'default_limit' => 10,

                /**
                 * Enable Highlighting
                 *
                 * Whether to highlight matched terms in search results.
                 */
                'highlight' => true,
            ],
        ],

        /**
         * Tinker Configuration
         *
         * Configure the tinker tool for executing PHP code.
         */
        'tinker' => [
            /**
             * PHP Binary Path
             *
             * Path to the PHP executable used for subprocess execution.
             * When null, auto-detection is used in this order:
             * 1. `which php` command
             * 2. PHP_BINARY constant
             *
             * Examples:
             * - null (auto-detect, recommended)
             * - '/usr/bin/php'
             * - '/usr/local/bin/php'
             * - '/opt/homebrew/bin/php'
             */
            'php_binary' => env('MCP_TINKER_PHP_BINARY', null),

            /**
             * Bin Path
             *
             * Path to the CakePHP bin directory containing the cake console.
             * When null, auto-detection is used in this order:
             * 1. ROOT constant + /bin
             * 2. Current working directory + /bin
             *
             * Examples:
             * - null (auto-detect, recommended)
             * - '/var/www/myapp/bin'
             */
            'bin_path' => env('MCP_TINKER_BIN_PATH', null),
        ],

        /**
         * Prompt Configuration
         *
         * Configure behavior of MCP prompts.
         */
        'prompts' => [
            /**
             * CakePHP Version
             *
             * The CakePHP version to reference in prompt responses.
             * This allows targeting specific version documentation and conventions.
             *
             * Examples: '5.x', '5.2', '4.5', '4.x'
             */
            'cakephp_version' => env('MCP_CAKEPHP_VERSION', '5.x'),

            /**
             * PHP Version
             *
             * The PHP version to reference in prompt responses for type hints,
             * features, and best practices recommendations.
             *
             * Examples: '8.2', '8.3', '8.4'
             */
            'php_version' => env('MCP_PHP_VERSION', '8.2+'),

            /**
             * Quality Assurance Tools Configuration
             *
             * Configure which quality assurance tools are enabled and their settings.
             * These settings are used by the quality-assurance prompt to provide
             * relevant guidelines and best practices.
             */
            'quality_tools' => [
                /**
                 * PHPCS (PHP CodeSniffer)
                 *
                 * Static analysis tool for detecting violations of coding standards.
                 */
                'phpcs' => [
                    'enabled' => true,
                    'standard' => 'cakephp', // 'cakephp', 'PSR12', or custom path
                    'extensions' => ['php'],
                ],

                /**
                 * PHPStan
                 *
                 * Static analysis tool for finding bugs in PHP code.
                 */
                'phpstan' => [
                    'enabled' => true,
                    'level' => 8, // 0-9 or 'max'
                    'baseline' => false,
                ],

                /**
                 * PHPUnit
                 *
                 * Testing framework for PHP.
                 */
                'phpunit' => [
                    'enabled' => true,
                    'coverage' => true,
                    'coverage_threshold' => 80,
                ],

                /**
                 * Rector
                 *
                 * Tool for automated refactoring and code modernization.
                 */
                'rector' => [
                    'enabled' => false,
                    'set' => 'cakephp', // 'cakephp', 'php82', 'php83', etc.
                ],

                /**
                 * Psalm
                 *
                 * Alternative static analysis tool (can be used instead of or alongside PHPStan).
                 */
                'psalm' => [
                    'enabled' => false,
                    'level' => 3, // 1-8 (1 is strictest)
                ],
            ],
        ],
    ],
];
