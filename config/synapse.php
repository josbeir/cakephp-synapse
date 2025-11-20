<?php
declare(strict_types=1);

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
            'version' => env('MCP_SERVER_VERSION', '1.0.0'),
        ],

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
    ],
];
