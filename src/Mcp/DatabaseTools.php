<?php
declare(strict_types=1);

namespace Synapse\Mcp;

use Cake\Database\Schema\CollectionInterface;
use Cake\Datasource\ConnectionManager;
use Exception;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Database Tools
 *
 * MCP tools for inspecting and interacting with database connections and schema.
 */
class DatabaseTools
{
    /**
     * List all configured database connections.
     *
     * Returns information about all configured database connections including
     * driver type, database name, host, connection status, and whether it's
     * the default connection.
     *
     * @return array{connections: array<int, array<string, mixed>>} List of database connections
     */
    #[McpTool(
        name: 'database_connections',
        description: 'Inspect available database connections, including the default connection',
    )]
    public function listConnections(): array
    {
        $connections = ConnectionManager::configured();
        $defaultConnection = 'default';
        $result = [];

        foreach ($connections as $name) {
            try {
                /** @var \Cake\Database\Connection $connection */
                $connection = ConnectionManager::get($name);
                $config = $connection->config();

                // Test connection by attempting to get the driver
                $connected = true;
                try {
                    $connection->getDriver();
                } catch (Exception $e) {
                    $connected = false;
                }

                $result[] = [
                    'name' => $name,
                    'driver' => $connection->getDriver()::class,
                    'database' => $config['database'] ?? null,
                    'host' => $config['host'] ?? null,
                    'connected' => $connected,
                    'isDefault' => $name === $defaultConnection,
                ];
            } catch (Exception $e) {
                $message = sprintf("Failed to get connection info for '%s': %s", $name, $e->getMessage());
                throw new ToolCallException($message);
            }
        }

        return ['connections' => $result];
    }

    /**
     * Read database schema information.
     *
     * Returns detailed schema information for database tables including
     * columns, constraints, and indexes. Can describe a single table or
     * all tables in the database.
     *
     * @param string $connection Connection name (defaults to 'default')
     * @param string|null $table Specific table name (optional, if omitted returns all tables)
     * @return array<string, mixed> Schema information
     */
    #[McpTool(
        name: 'database_schema',
        description: 'Read database schema for tables, columns, constraints, and indexes',
    )]
    public function describeSchema(string $connection = 'default', ?string $table = null): array
    {
        try {
            /** @var \Cake\Database\Connection $conn */
            $conn = ConnectionManager::get($connection);
            $schemaCollection = $conn->getSchemaCollection();

            if ($table !== null) {
                // Describe single table
                $tableSchema = $this->describeTable($schemaCollection, $table);

                return [
                    'connection' => $connection,
                    'table' => $tableSchema,
                ];
            }

            // Describe all tables
            $tables = $schemaCollection->listTablesWithoutViews();
            $result = [];

            foreach ($tables as $tableName) {
                $result[] = $this->describeTable($schemaCollection, $tableName);
            }

            return [
                'connection' => $connection,
                'tableCount' => count($tables),
                'tables' => $result,
            ];
        } catch (Exception $exception) {
            $message = sprintf("Failed to read schema for connection '%s': %s", $connection, $exception->getMessage());
            throw new ToolCallException($message);
        }
    }

    /**
     * Describe a single database table.
     *
     * Extracts detailed schema information for a specific table including
     * all columns with their types and constraints, table constraints,
     * and indexes.
     *
     * @param \Cake\Database\Schema\CollectionInterface $collection Schema collection
     * @param string $tableName Table name to describe
     * @return array<string, mixed> Table schema information
     */
    private function describeTable(CollectionInterface $collection, string $tableName): array
    {
        $schema = $collection->describe($tableName);

        $columns = [];
        foreach ($schema->columns() as $columnName) {
            $column = $schema->getColumn($columnName);
            $columns[] = [
                'name' => $columnName,
                'type' => $column['type'] ?? 'unknown',
                'length' => $column['length'] ?? null,
                'precision' => $column['precision'] ?? null,
                'null' => $column['null'] ?? false,
                'default' => $column['default'] ?? null,
                'comment' => $column['comment'] ?? '',
            ];
        }

        $constraints = [];
        foreach ($schema->constraints() as $constraintName) {
            $constraint = $schema->getConstraint($constraintName);
            $constraints[$constraintName] = [
                'type' => $constraint['type'] ?? 'unknown',
                'columns' => $constraint['columns'] ?? [],
            ];
        }

        $indexes = [];
        foreach ($schema->indexes() as $indexName) {
            $index = $schema->getIndex($indexName);
            $indexes[$indexName] = [
                'columns' => $index['columns'] ?? [],
                'type' => $index['type'] ?? null,
            ];
        }

        return [
            'name' => $tableName,
            'columns' => $columns,
            'constraints' => $constraints,
            'indexes' => $indexes,
        ];
    }
}
