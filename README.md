[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/josbeir/cakephp-synapse)
[![Build Status](https://github.com/josbeir/cakephp-synapse/actions/workflows/ci.yml/badge.svg)](https://github.com/josbeir/cakephp-synapse/actions)
[![codecov](https://codecov.io/github/josbeir/cakephp-synapse/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-synapse)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net/releases/8.2/en.php)
[![CakePHP Version](https://img.shields.io/badge/CakePHP-5.2%2B-red.svg)](https://cakephp.org/)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-synapse)](https://packagist.org/packages/josbeir/cakephp-synapse)

# Synapse: a CakePHP MCP-Server plugin

Expose your CakePHP application functionality via the Model Context Protocol (MCP).

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [Installing the Plugin](#installing-the-plugin)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Creating MCP Tools](#creating-mcp-tools)
- [Built-in Tools](#built-in-tools)
  - [System Tools](#system-tools)
  - [Database Tools](#database-tools)
  - [Route Tools](#route-tools)
  - [Documentation Search](#documentation-search)
- [Running the Server](#running-the-server)
- [Discovery Caching](#discovery-caching)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Overview

Synapse is a CakePHP plugin that implements the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/), allowing AI assistants and other MCP clients to interact with your CakePHP application through a standardized interface.

> [!WARNING]
> This plugin uses the [MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk) which is currently in active development. Features and APIs may change as the SDK evolves.

The Model Context Protocol is an open protocol that enables seamless integration between AI assistants (like Claude) and your application's data and functionality. With Synapse, you can expose:

- **Tools**: Functions that AI assistants can call (e.g., database queries, route inspection)
- **Resources**: Data sources that can be read
- **Prompts**: Pre-configured templates for common tasks

## Features

- 🚀 **Easy Integration**: Add MCP capabilities to your CakePHP app in minutes
- 🔍 **Auto-Discovery**: Automatically discovers MCP elements using PHP 8 attributes
- 🛠️ **Built-in Tools**: Pre-built tools for system info, database inspection, and route management
- 📚 **Documentation Search**: Full-text search powered by SQLite FTS5 with BM25 ranking (indexes CakePHP's official markdown documentation)
- 📦 **Extensible**: Create custom tools using simple PHP attributes

## Installation

### Requirements

- PHP 8.2 or higher
- CakePHP 5.2 or higher

### Installing the Plugin

Install via Composer as a development dependency:

```bash
composer require --dev josbeir/cakephp-synapse
```

> [!NOTE]
> This plugin is typically used as a development tool to allow AI assistants to interact with your application during development. It should not be installed in production environments.

```bash
bin/cake plugin load --only-cli --optional Synapse 
```

The plugin will automatically register itself and discover MCP elements in your application.

## Quick Start

1. **Install the plugin** (see above)

2. **Configure your MCP-enabled client**:

To connect Claude Code/Desktop or other MCP clients (VSCode/Zed/...):

1. Configure the client to use stdio transport
2. Point it to your CakePHP bin directory: `bin/cake synapse server`
3. The client will communicate with your app via the MCP protocol

Most clients require a **command** wich will be your cake executable `bin/cake` followed by **arguments**: `synapse server`

```json
{
  "my-cakephp-app": {
    "command": "bin/cake",
    "args": ["synapse", "server"]
  }
}
```

Or run when using `DDEV` instance

```json
{
  "my-cakephp-app": {
    "command": "ddev",
    "args": ["cake", "synapse", "server"],
  }
}
```

3. **Try it out** - The AI assistant can now:
   - Query your database schema
   - Inspect routes
   - Read configuration
   - And more!

> [!TIP]
> Use the MCP inspector tool to quickly see and test the available tools in action
> ```bash
> $ npx @modelcontextprotocol/inspector bin/cake synapse server
> ```

## Configuration

Various configuration options are available for Synapse. Refer to `config/synapse.php` in this plugin for details on available settings and customization.

## Creating MCP Tools

Create custom tools by adding the `#[McpTool]` attribute to public methods:

```php
<?php
namespace App\Mcp;

use Mcp\Capability\Attribute\McpTool;

class MyTools
{
    #[McpTool(
        name: 'get_user',
        description: 'Fetch a user by ID'
    )]
    public function getUser(int $id): array
    {
        $usersTable = $this->fetchTable('Users');
        $user = $usersTable->get($id);
        
        return [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }
    
    #[McpTool(name: 'list_users')]
    public function listUsers(int $limit = 10): array
    {
        $usersTable = $this->fetchTable('Users');
        $users = $usersTable->find()
            ->limit($limit)
            ->toArray();
            
        return [
            'total' => count($users),
            'users' => $users,
        ];
    }
}
```

The plugin will automatically discover these tools and make them available to MCP clients.

### Tool Parameters

Tools support typed parameters with automatic validation:

```php
#[McpTool(name: 'search_articles')]
public function searchArticles(
    string $query,
    int $limit = 20,
    bool $publishedOnly = true
): array {
    // Implementation
}
```

## Built-in Tools

Synapse includes several built-in tools for common operations:

### System Tools

Access system information and configuration:

- `system_info` - Get CakePHP version, PHP version, debug mode, etc.
- `config_read` - Read configuration values
- `debug_status` - Check if debug mode is enabled
- `list_env_vars` - List all available environment variables

### Tinker Tool

Execute PHP code in the CakePHP application context:

- `tinker` - Execute arbitrary PHP code with full application context

**⚠️ Warning**: This tool executes arbitrary code in your application. Use responsibly and avoid modifying data without explicit approval.

### Database Tools

Inspect and query your database:

- `list_connections` - List all configured database connections
- `describe_schema` - Get detailed schema information for tables
  - View all tables in a connection
  - Inspect columns, constraints, indexes
  - Understand foreign key relationships

### Route Tools

Inspect and analyze your application routes:

- `list_routes` - List all routes with filtering and sorting
- `get_route` - Get detailed information about a specific route
- `match_url` - Find which route matches a given URL
- `detect_route_collisions` - Find potential route conflicts

### Documentation Search

Search CakePHP documentation with full-text search powered by SQLite FTS5:

- `search_docs` - Search documentation with relevance ranking, fuzzy matching, and filtering
- `get_doc` - Retrieve full document content by document ID (format: `source::path`)
- `docs_stats` - View index statistics and available sources
- `docs://search/{query}` - Resource for accessing formatted search results
- `docs://{documentId}` - Resource for accessing a specific document by ID

> [!NOTE]
> Documentation is indexed from the official [CakePHP markdown documentation](https://github.com/cakephp/docs-md). The index is built locally using SQLite FTS5 for fast, dependency-free full-text search.

Use the CLI to manage and search the index:

```bash
# Index all sources
bin/cake synapse index

# Index specific source
bin/cake synapse index --source cakephp-5x

# Force re-index and optimize
bin/cake synapse index --force --optimize

# Search documentation from CLI (interactive mode by default)
bin/cake synapse search "authentication"

# Search with options
bin/cake synapse search "database queries" --limit 5 --fuzzy --detailed

# Non-interactive mode for scripts/CI
bin/cake synapse search "authentication" --non-interactive

# Interactive features:
# - View result details and snippets
# - Navigate between results
# - View full document content
# - All from within the CLI
```

## Running the Server

Start the MCP server using the CLI command:

```bash
# Start with default settings (stdio transport)
bin/cake synapse server

# Start with verbose output
bin/cake synapse server --verbose

# Disable caching
bin/cake synapse server --no-cache

# View help
bin/cake synapse server --help
```

### Command Options

- `--transport`, `-t` - Transport type (currently only `stdio` is supported)
- `--log`, `-l` - Enable MCP server logging to specified log engine (e.g., `debug`, `error`)
- `--no-cache`, `-n` - Disable discovery caching for this run
- `--clear-cache`, `-c` - Clear discovery cache before starting
- `--verbose`, `-v` - Enable verbose output (pipes logging to stderr)
- `--quiet`, `-q` - Suppress all output except errors

### Transport Options

Currently, Synapse supports:
- **stdio** - Standard input/output (default, recommended for most MCP clients)

Future versions may include HTTP/SSE transport.

## Discovery Caching

Discovery caching dramatically improves server startup performance by caching the discovered MCP elements (tools, resources, prompts). This can reduce startup time by up to 99%!

> [!NOTE]
> While caching improves performance, remember that this plugin is intended for development use. The caching feature is most useful when running the MCP server frequently during development sessions.

### Configuration

Synapse uses CakePHP's built-in PSR-16 cache system. Configure caching in `config/synapse.php`:

```php
return [
    'Synapse' => [
        'discovery' => [
            'scanDirs' => ['src', 'plugins/Synapse/src'],
            'excludeDirs' => ['tests', 'vendor', 'tmp', 'logs', 'webroot'],

            // Cache configuration (defaults to 'default')
            'cache' => 'default',  // or 'mcp', or any cache config name
        ],
    ],
];
```

### Command Options

```bash
# Disable caching for this run
bin/cake synapse server --no-cache
bin/cake synapse server -n

# Clear cache before starting
bin/cake synapse server --clear-cache
bin/cake synapse server -c

# Combine options
bin/cake synapse server --clear-cache --verbose
```

### Performance

- **Without cache**: ~100-500ms startup time (depending on codebase size)
- **With cache**: ~1-5ms startup time (99% improvement!)
- **Recommendation**: Enable caching for faster startup times during development

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run PHPStan analysis
composer phpstan

# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

## Contributing

Contributions are welcome! Please follow these guidelines:

1. **Code Standards**: Follow CakePHP coding standards
2. **Tests**: Add tests for new features
3. **PHPStan**: Ensure level 8 compliance
4. **Documentation**: Update README for new features

### Development Setup

```bash
# Clone the repository
git clone https://github.com/josbeir/cakephp-synapse.git
cd synapse

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer phpstan
```

## License

This plugin is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

- Built with [CakePHP](https://cakephp.org/)
- Implements [Model Context Protocol](https://modelcontextprotocol.io/)
- Uses the [MCP PHP SDK](https://github.com/modelcontextprotocol/php-sdk)

> [!NOTE]
> The MCP PHP SDK is in active development and APIs may change.
