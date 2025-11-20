[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://github.com/josbeir/cakephp-synapse)
[![Build Status](https://github.com/josbeir/cakephp-synapse/actions/workflows/ci.yml/badge.svg)](https://github.com/josbeir/cakephp-synapse/actions)
[![codecov](https://codecov.io/github/josbeir/cakephp-synapse/graph/badge.svg?token=4VGWJQTWH5)](https://codecov.io/github/josbeir/cakephp-synapse)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net/releases/8.2/en.php)
[![CakePHP Version](https://img.shields.io/badge/CakePHP-5.2%2B-red.svg)](https://cakephp.org/)
[![Packagist Downloads](https://img.shields.io/packagist/dt/josbeir/cakephp-synapse)](https://packagist.org/packages/josbeir/cakephp-synapse)

# Synapse: a CakePHP MCP-Server plugin

Expose your CakePHP application functionality via the Model Context Protocol (MCP).

[![MCP](https://modelcontextprotocol.io/static/mcp-logo.svg)](https://modelcontextprotocol.io/)

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Creating MCP Tools](#creating-mcp-tools)
- [Built-in Tools](#built-in-tools)
  - [System Tools](#system-tools)
  - [Database Tools](#database-tools)
  - [Route Tools](#route-tools)
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
- **Resources**: Data sources that can be read (coming soon)
- **Prompts**: Pre-configured templates for common tasks (coming soon)

## Features

- 🚀 **Easy Integration**: Add MCP capabilities to your CakePHP app in minutes
- 🔍 **Auto-Discovery**: Automatically discovers MCP elements using PHP 8 attributes
- 🛠️ **Built-in Tools**: Pre-built tools for system info, database inspection, and route management
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
cake plugin load --only-cli --optional Synapse 
```

The plugin will automatically register itself and discover MCP elements in your application.

## Quick Start

1. **Install the plugin** (see above)

2. **Start the MCP server**:

```bash
bin/cake synapse server
```

3. **Connect an MCP client** (like Claude Desktop or another MCP-compatible tool) to `stdio` transport

4. **Try it out** - The AI assistant can now:
   - Query your database schema
   - Inspect routes
   - Read configuration
   - And more!

## Configuration

Create a configuration file at `config/synapse.php`:

```php
<?php
return [
    'Synapse' => [
        // Server information sent to MCP clients
        'serverInfo' => [
            'name' => 'My App MCP Server',
            'version' => '1.0.0',
        ],

        // MCP protocol version
        'protocolVersion' => '2024-11-05',

        // Discovery settings
        'discovery' => [
            'scanDirs' => ['src', 'plugins'],
            'excludeDirs' => ['tests', 'vendor', 'tmp'],
        ],
    ],
];
```

### Environment Variables

You can also configure using environment variables:

```bash
MCP_SERVER_NAME="My App MCP Server"
MCP_SERVER_VERSION="1.0.0"
MCP_PROTOCOL_VERSION="2024-11-05"
```

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
- `reverse_route` - Generate URLs from route names or parameters
- `detect_route_collisions` - Find potential route conflicts

## Running the Server

Start the MCP server using the CLI command:

```bash
# Start with default settings (stdio transport)
bin/cake synapse server

# Start with verbose output
bin/cake synapse server --verbose

# View help
bin/cake synapse server --help
```

### Transport Options

Currently, Synapse supports:
- **stdio** - Standard input/output (default, recommended for most MCP clients)

Future versions may include HTTP/SSE transport.

### Connecting MCP Clients

To connect Claude Desktop or other MCP clients:

1. Configure the client to use stdio transport
2. Point it to your CakePHP bin directory: `/path/to/your/app/bin/cake synapse server`
3. The client will communicate with your app via the MCP protocol

Example IDE/Tool configuration (VSCode/Claude/Zed/...)

```json
{
  "my-cakephp-app": {
    "command": "bin/cake",
    "args": ["synapse", "server"]
  }
}
```

Or run inside your DDEV instance

```json
{
  "cakephp-synapse": {
    "command": "ddev",
    "args": ["cake","synapse","server"],
  }
}
```

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

Or use an environment variable:

```bash
# Use default cache engine
MCP_DISCOVERY_CACHE=default

# Use a custom cache engine
MCP_DISCOVERY_CACHE=mcp
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
