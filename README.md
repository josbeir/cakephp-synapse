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
  - [Tinker Tool](#tinker-tool)
  - [Database Tools](#database-tools)
  - [Route Tools](#route-tools)
  - [Documentation Search](#documentation-search)
- [Built-in Prompts](#built-in-prompts)
  - [Available Prompts](#available-prompts)
  - [Using Prompts](#using-prompts)
  - [Configuring CakePHP Version](#configuring-cakephp-version)
  - [Quality Assurance Prompt](#quality-assurance-prompt)
- [Running the Server](#running-the-server)
  - [Command Options](#command-options)
  - [Testing with MCP Inspector](#testing-with-mcp-inspector)
  - [Transport Options](#transport-options)
- [Discovery Caching](#discovery-caching)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

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

> [!TIP]
> **After updating the plugin**, it's recommended to re-index the documentation to ensure all features work correctly:
> ```bash
> bin/cake synapse index -d  # Destroy old index
> bin/cake synapse index     # Re-index documentation
> ```

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

| Tool | Description |
|------|-------------|
| `system_info` | Get CakePHP version, PHP version, debug mode, etc. |
| `config_read` | Read configuration values |
| `debug_status` | Check if debug mode is enabled |
| `list_env_vars` | List all available environment variables |

### Tinker Tool

Execute PHP code in the CakePHP application context:

| Tool | Description |
|------|-------------|
| `tinker` | Execute arbitrary PHP code with full application context |

> [!WARNING]
> This tool executes arbitrary code in your application. Use responsibly and avoid modifying data without explicit approval.

### Database Tools

Inspect and query your database:

| Tool | Description |
|------|-------------|
| `database_connections` | List all configured database connections |
| `database_schema` | Get detailed schema information for tables (view all tables, inspect columns, constraints, indexes, understand foreign key relationships) |

> [!TIP]
> The `tinker` tool can be used to query the database using CakePHP's ORM. The tinker context provides access to `$this->fetchTable()` for easy database operations.

### Route Tools

Inspect and analyze your application routes:

| Tool | Description |
|------|-------------|
| `list_routes` | List all routes with filtering and sorting |
| `get_route` | Get detailed information about a specific route |
| `match_url` | Find which route matches a given URL |
| `detect_route_collisions` | Find potential route conflicts |

### Documentation Search

Search CakePHP documentation with full-text search powered by SQLite FTS5:

| Tool | Description |
|------|-------------|
| `search_docs` | Search documentation with relevance ranking, fuzzy matching, and filtering |
| `get_doc` | Retrieve full document content by document ID (format: `source::path`) |
| `docs_stats` | View index statistics and available sources |

> [!NOTE]
> Documentation is indexed from the official [CakePHP markdown documentation](https://github.com/cakephp/docs-md). The index is built locally using SQLite FTS5 for fast, dependency-free full-text search.

### Resources

Access documentation through resource templates:

| Resource | Description |
|----------|-------------|
| `docs://search/{query}` | Search CakePHP documentation and return formatted results |
| `docs://content/{documentId}` | Retrieve full document content by document ID (format: `source::path`) |

## Built-in Prompts

Synapse includes pre-defined prompt workflows that guide LLMs through common CakePHP development tasks. Prompts combine multiple tools (search docs, read documentation, tinker) into structured, best-practice workflows.

### Available Prompts

| Prompt | Description | Arguments |
|--------|-------------|-----------|
| `documentation-expert` | Get comprehensive guidance on CakePHP features with examples | `topic` (required), `depth` (optional: basic/intermediate/advanced) |
| `debug-helper` | Systematic debugging workflow for errors and issues | `error` (required), `context` (optional: controller/model/database/view) |
| `feature-builder` | Guide for implementing complete features following conventions | `feature` (required), `component` (optional: controller/model/behavior/helper/middleware/command/full-stack) |
| `database-explorer` | Explore database schema, relationships, and data | `table` (required), `show` (optional: schema/data/relationships/all) |
| `code-reviewer` | Review code against CakePHP conventions and best practices | `code` (required), `focus` (optional: conventions/security/performance/testing/all) |
| `migration-guide` | Help migrate code between CakePHP versions | `fromVersion`, `toVersion` (required), `area` (optional: specific feature or general) |
| `testing-assistant` | Generate test cases and testing guidance | `subject` (required), `testType` (optional: unit/integration/fixture/all) |
| `performance-analyzer` | Analyze and optimize performance issues | `concern` (required), `context` (optional: code snippet or description) |
| `orm-query-helper` | Build complex ORM queries with guidance | `queryGoal` (required), `tables` (optional: comma-separated list) |
| `tinker-workshop` | Interactive PHP exploration and testing guide | `goal` (required: explore/test/debug), `subject` (optional) |
| `quality-assurance` | Coding guidelines and QA best practices for CakePHP | `context` (optional: guidelines/integration/troubleshooting/all), `tools` (optional: all or comma-separated list) |

### Using Prompts

Prompts are workflow templates that guide the LLM through multi-step processes. When using an MCP client like Claude:

```
"Use the documentation-expert prompt to learn about CakePHP Authentication"

"Use debug-helper with error='Call to undefined method' and context='controller'"

"Use feature-builder to implement a REST API endpoint"
```

**Prompts automatically:**
- Search relevant documentation
- Read detailed guides
- Execute test code via tinker
- Provide structured, comprehensive answers following best practices

**Benefits:**
- **Faster workflows** - Common tasks become one-step operations
- **Best practices** - Prompts encode CakePHP expertise and conventions
- **Consistency** - Standardized approaches to common problems
- **Discovery** - See available workflows without remembering tool combinations

### Configuring CakePHP Version

Prompts reference a specific CakePHP version in their guidance. Configure this in `config/synapse.php`:

```php
return [
    'Synapse' => [
        'prompts' => [
            // Target CakePHP version for prompt responses
            // Examples: '5.x', '5.2', '4.5', '4.x'
            'cakephp_version' => env('MCP_CAKEPHP_VERSION', '5.x'),
        ],
    ],
];
```

Or set via environment variable:

```bash
export MCP_CAKEPHP_VERSION=5.2
bin/cake synapse server
```

This allows targeting specific version documentation and conventions when working with different CakePHP versions.

### Quality Assurance Prompt

The `quality-assurance` prompt provides comprehensive coding guidelines and QA best practices for CakePHP development. It supports multiple quality tools including PHPCS, PHPStan, PHPUnit, Rector, and Psalm.

Configure which tools are enabled and their settings in `config/synapse.php`:

```php
'Synapse' => [
    'prompts' => [
        'quality_tools' => [
            'phpcs' => ['enabled' => true, 'standard' => 'cakephp'],
            'phpstan' => ['enabled' => true, 'level' => 8],
            'phpunit' => ['enabled' => true, 'coverage' => true],
            'rector' => ['enabled' => false, 'set' => 'cakephp'],
            'psalm' => ['enabled' => false, 'level' => 3],
        ],
    ],
],
```

**Context options:** guidelines, integration, troubleshooting, all
**Tools options:** all, or comma-separated list (phpcs, phpstan, phpunit, rector, psalm)

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

# Launch MCP Inspector for testing (requires Node.js/npx)
bin/cake synapse server --inspect

# View help
bin/cake synapse server --help
```

### Command Options

| Option | Short | Description |
|--------|-------|-------------|
| `--transport` | `-t` | Transport type (currently only `stdio` is supported) |
| `--no-cache` | `-n` | Disable discovery caching for this run |
| `--clear-cache` | `-c` | Clear discovery cache before starting |
| `--inspect` | `-i` | Launch MCP Inspector to test the server interactively (requires Node.js/npx) |
| `--verbose` | `-v` | Enable verbose output (pipes logging to stderr) |
| `--quiet` | `-q` | Suppress all output except errors |

### Testing with MCP Inspector

The `--inspect` flag launches the [MCP Inspector](https://github.com/modelcontextprotocol/inspector), a development tool that provides a web-based UI for testing your MCP server:

```bash
bin/cake synapse server --inspect
```

This will:
1. Start the MCP Inspector (downloads automatically via npx if not installed)
2. Launch the server in inspector mode
3. Open a web browser with an interactive UI
4. Allow you to test tools, resources, and prompts interactively

**Requirements:**
- Node.js and npx must be installed
- A web browser

The inspector is invaluable during development for:
- Testing tool functionality
- Inspecting resource data
- Debugging server behavior
- Verifying tool schemas and documentation

### Transport Options

Currently, Synapse supports:
- **stdio** - Standard input/output (default, recommended for most MCP clients)

Future versions may include HTTP/SSE transport.

## Discovery Caching

Discovery caching improves server startup performance by caching the discovered MCP elements (tools, resources, prompts).

### Configuration

Synapse uses CakePHP's built-in PSR-16 cache system. Configure caching in `config/synapse.php`

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
