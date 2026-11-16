[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/josbeir/cakephp-synapse)
[![Build Status](https://github.com/josbeir/cakephp-synapse/actions/workflows/ci.yml/badge.svg?branch=cake6)](https://github.com/josbeir/cakephp-synapse/actions?query=branch%3Acake6)
[![codecov](https://codecov.io/github/josbeir/cakephp-synapse/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-synapse)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.5%2B-blue.svg)](https://www.php.net/releases/8.5/en.php)
[![CakePHP Version](https://img.shields.io/badge/CakePHP-6.x-red.svg)](https://cakephp.org/)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-synapse)](https://packagist.org/packages/josbeir/cakephp-synapse)

# Synapse: a CakePHP MCP-Server plugin

Expose your CakePHP application functionality via the Model Context Protocol (MCP).

> [!NOTE]
> The `cake6` branch targets the upcoming CakePHP 6 release and requires PHP 8.5 or higher, matching the current [CakePHP 6 requirements](https://github.com/cakephp/cakephp/blob/6.x/composer.json). For CakePHP 5 applications, use the stable plugin releases on `main`.

![CakePHP Synapse Hero](/docs/hero.jpg)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
  - [Requirements](#requirements)
  - [Installing the Plugin](#installing-the-plugin)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Built-in Tools](#built-in-tools)
- [Built-in Prompts](#built-in-prompts)
  - [Creating Custom Tools, Resources, and Prompts](#creating-custom-tools-resources-and-prompts)
- [CLI usage](#cli-usage)
- [Running the Server](#running-the-server)
  - [Testing with MCP Inspector](#testing-with-mcp-inspector)
- [Upgrading from 0.x](#upgrading-from-0x)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

## Overview

Synapse provides CakePHP development capabilities over the [Model Context Protocol (MCP)](https://modelcontextprotocol.io/). It uses [Crustum MCP](https://github.com/Crustum/mcp) for protocol handling, local stdio transport, explicit capability registration, and Inspector integration.

The Model Context Protocol is an open protocol that enables seamless integration between AI assistants (like Claude) and your application's data and functionality. With Synapse, you can expose:

- **Tools**: Functions that AI assistants can call (e.g., database queries, route inspection)
- **Resources**: Data sources that can be read
- **Prompts**: Pre-configured templates for common tasks

## Features

- 🚀 **Rapid Integration**: Enable MCP support in your CakePHP app with minimal setup
- 🔍 **Explicit Capability Catalog**: Built-in tools, resources, and prompts with stable names, schemas, and annotations
- 🛠️ **Built-in Tools**: Includes tools for system info, database schema exploration, route inspection, code execution, log inspection, cache management, ORM introspection, and more
- 📚 **Advanced Documentation Search**: Full-text search with fuzzy matching and relevance ranking, powered by SQLite FTS5 (indexes official CakePHP markdown docs)
- 🧩 **Customizable & Extensible**: Easily define your own tools, resources, and prompts using attributes and configuration
- ⚡ **Prompt Workflows**: Built-in prompt flows for common development tasks, code review, debugging, and feature building

## Installation

### Requirements

- PHP 8.5 or higher
- CakePHP 6.x (currently a development release)
- SQLite3 with FTS5, intl, and the extensions required by Composer's dependency resolution
- `ext-sodium` for the current OAuth dependency on PHP 8.5

Crustum currently registers Bake console commands unconditionally, so CakePHP Bake is included as a runtime dependency. Synapse does not load Bake or Tessera as application plugins and does not require OAuth tables.

This branch uses CakePHP `6.x-dev`, Crustum MCP `2.x-dev`, and Bake `4.x-dev`. Their development dependencies include Chronos 4, Tessera 2, Authentication 5, and TwigView 3; PHPUnit 13 and the CakePHP 6 coding standard are used for validation.

### Installing the Plugin

While CakePHP 6 and its dependencies are in development, set the following in your application's `composer.json` to allow development dependencies while preferring stable releases:

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Install the CakePHP 6 branch via Composer as a development dependency:

```bash
composer require --dev josbeir/cakephp-synapse:dev-cake6 --with-all-dependencies
```

> [!NOTE]
> This plugin is typically used as a development tool to allow AI assistants to interact with your application during development. It should not be installed in production environments.

Load the plugins in this order in your application's `config/plugins.php`:

```php
return [
    'Crustum/Mcp' => [
        'onlyCli' => true,
        'optional' => true,
        'routes' => false,
        'middleware' => false,
    ],
    'Synapse' => ['onlyCli' => true, 'optional' => true],
];
```

For an application that loads plugins programmatically, use the same plugin names and options with `$this->addPlugin()`. Use the full `Crustum/Mcp` name so configuration loading resolves the already loaded plugin.

Synapse registers its local server as `synapse`. This setup enables no HTTP endpoints or HTTP middleware.

The existing documentation index remains compatible with 1.0. See the [upgrade guide](docs/UPGRADING.md) when migrating from 0.x.

## Quick Start

1. **Install the plugin** (see above)

2. **Configure your MCP-enabled client**:

To connect Claude Code/Desktop or other MCP clients (VSCode/Zed/...):

1. Configure the client to use stdio transport
2. Point it to your CakePHP bin directory: `bin/cake mcp start synapse`
3. The client will communicate with your app via the MCP protocol

Most clients require a **command** which will be your cake executable `bin/cake` followed by **arguments**: `mcp start synapse`

```json
{
  "my-cakephp-app": {
    "command": "bin/cake",
    "args": ["mcp", "start", "synapse"]
  }
}
```

Or run when using `DDEV` instance

```json
{
  "my-cakephp-app": {
    "command": "ddev",
    "args": ["cake", "mcp", "start", "synapse"]
  }
}
```

## Configuration

Defaults live in `config/synapse.php`. Override them under the `Synapse` key in your application configuration, or create `config/app_synapse.php`. Documentation indexing, prompt settings, Tinker paths, server metadata, and instructions remain configurable.

Additional capabilities belong under `Synapse.capabilities.tools`, `.prompts`, and `.resources`. Crustum registration belongs under `Mcp.local`. Hosts can override `Mcp.local.synapse` with their own server class.

Capabilities are registered explicitly on each process startup. Synapse no longer scans application directories or caches discovered classes. Crustum handles protocol negotiation and response cache hints.



## Built-in Tools

Synapse includes several built-in tools and resources for common operations:

| Category | Name | Description |
|----------|------|-------------|
| System | `system_info` | Get CakePHP version, PHP version, debug mode, etc. |
| System | `config_read` | Read configuration values with sensitive keys redacted |
| System | `debug_status` | Check if debug mode is enabled |
| System | `list_env_vars` | List available environment variable names with values redacted |
| Code Execution | `tinker` | Execute arbitrary PHP code with full application context |
| Database | `database_connections` | List all configured database connections |
| Database | `database_schema` | Get detailed schema information for tables (view all tables, inspect columns, constraints, indexes, understand foreign key relationships) |
| Routes | `list_routes` | List all routes with filtering and sorting |
| Routes | `get_route` | Get detailed information about a specific route |
| Routes | `match_url` | Find which route matches a given URL |
| Routes | `detect_route_collisions` | Find potential route conflicts |
| Documentation | `search_docs` | Search documentation with relevance ranking, fuzzy matching, and filtering |
| Documentation | `get_doc` | Retrieve full document content by document ID (format: `source::path`) |
| Documentation | `docs_stats` | View index statistics and available sources |
| Documentation | `docs://search/{query}` | Search CakePHP documentation and return formatted results |
| Documentation | `docs://content/{docId}` | Retrieve full document content by document ID (URI-encoded `source::path`) |
| Commands | `list_commands` | List all available CakePHP console commands with optional filtering and sorting |
| Commands | `get_command_info` | Get detailed information about a specific console command (options, arguments, help) |
| Commands | `run_command` | Run a registered CakePHP console command in a subprocess with configurable timeout |
| Logs | `log_list` | List all log files in the application logs directory with size and modification time |
| Logs | `log_read` | Read the last N lines from a log file, optionally filtered by log level (error, warning, info, debug, …) |
| Cache | `cache_configs` | List all configured CakePHP cache configurations with engine information |
| Cache | `cache_read` | Read a value from a cache configuration by key |
| Cache | `cache_write` | Write a string value to a cache configuration |
| Cache | `cache_delete` | Delete a key from a cache configuration |
| Cache | `cache_clear` | Clear all entries from a cache configuration |
| ORM | `orm_describe` | Describe a CakePHP ORM Table: associations, behaviors, display field, primary key, entity class |
| ORM | `orm_find` | Run a find query (`all`, `first`, or `count`) on an ORM Table with a configurable row limit |

> [!WARNING]
> The `tinker` tool executes arbitrary code in your application. Use responsibly and avoid modifying data without explicit approval.

> [!TIP]
> The `tinker` tool can be used to query the database using CakePHP's ORM. The tinker context provides access to `$this->fetchTable()` for easy database operations.

> [!NOTE]
> Documentation is indexed from the official [CakePHP markdown documentation](https://github.com/cakephp/docs-md). The index is built locally using SQLite FTS5 for fast, dependency-free full-text search.

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

Prompts return guidance for the agent to follow using the available tools. They do not invoke tools or execute code themselves.

**Benefits:**
- **Faster workflows** - Common tasks become one-step operations
- **Best practices** - Prompts encode CakePHP expertise and conventions
- **Consistency** - Standardized approaches to common problems
- **Discovery** - See available workflows without remembering tool combinations

### Configuring Prompts

Prompts can reference a specific CakePHP version and use various quality tools. Configure both in `config/synapse.php`:

```php
return [
    'Synapse' => [
        'prompts' => [
            // Target CakePHP version for prompt responses (e.g. '6.x', '6.0')
            'cakephp_version' => env('MCP_CAKEPHP_VERSION', '6.x'),

            // Target PHP version for prompt responses (e.g. '8.5', '8.x')
            'php_version' => env('MCP_PHP_VERSION', PHP_VERSION),

            // Quality tools configuration
            'quality_tools' => [
                'phpcs' => ['enabled' => true, 'standard' => 'cakephp'],
                'phpstan' => ['enabled' => true, 'level' => 8],
                'phpunit' => ['enabled' => true, 'coverage' => true],
                'rector' => ['enabled' => false, 'set' => 'cakephp'],
                'psalm' => ['enabled' => false, 'level' => 3],
            ],
        ],
    ],
];
```

### Creating Custom Tools, Resources, and Prompts

Create classes extending Crustum's `Tool`, `Prompt`, or `Resource`, using its supported names, descriptions, schemas, and response API. Register additional classes explicitly:

```php
return [
    'Synapse' => [
        'capabilities' => [
            'tools' => [\App\Mcp\Tools\ApplicationInfoTool::class],
            'prompts' => [],
            'resources' => [],
        ],
    ],
];
```

These lists append to Synapse's built-in catalog. Duplicate names and invalid classes fail at startup. Register dependencies in the CakePHP container; capability handlers can receive services through method injection. Crustum's `shouldRegister()` conditions apply to both listing and invocation.

See the [complete custom-tool migration example](docs/UPGRADING.md#custom-capabilities) and [Crustum integration documentation](https://github.com/Crustum/mcp/blob/main/docs/index.md).

## CLI usage

Use the CLI to manage and search the index:

```bash
# Index all sources
bin/cake synapse index

# Index specific source
bin/cake synapse index --source cakephp-6x

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

Configure your MCP client to launch the local server from the application directory:

```bash
bin/cake mcp start synapse
```

The command reads MCP requests from stdin and writes only protocol responses to stdout. Configure application logging to use files or stderr.

### Testing with MCP Inspector

Crustum provides the native Inspector command:

```bash
bin/cake mcp inspector synapse
```

Inspector requires Node.js/npx and a browser. It can inspect capability metadata and invoke tools, prompts, and resources.

Synapse officially supports local stdio. Remote HTTP servers, OAuth configuration, and MCP Apps are outside this release's integration.

## Upgrading from 0.x

Version 1.0 replaces the SDK implementation with Crustum MCP and requires CakePHP 5.4+. Update client launch arguments and migrate SDK attribute capabilities to explicit Crustum classes. The old server command, discovery cache flags, and scanning configuration have been removed.

On `cake6`, the framework requirement is CakePHP 6 with PHP 8.5+. The Crustum capability API and `bin/cake mcp start synapse` entry point remain the same. Custom commands must use CakePHP 6's parameterless `execute()` method and its `$this->args` and `$this->io` properties; container imports now use `Cake\Container`.

See [UPGRADING.md](docs/UPGRADING.md) for the migration checklist, configuration mapping, and custom-tool example.

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
- Built on [Crustum MCP](https://github.com/Crustum/mcp)
