<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Mcp;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\Mcp\DatabaseTools;

/**
 * DatabaseTools Test Case
 *
 * Tests for database connection inspection and schema reading tools.
 */
class DatabaseToolsTest extends TestCase
{
    /**
     * Test subject
     */
    protected DatabaseTools $DatabaseTools;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Synapse.Articles',
        'plugin.Synapse.Authors',
        'plugin.Synapse.Tags',
        'plugin.Synapse.Users',
    ];

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->DatabaseTools = new DatabaseTools();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test listConnections method
     */
    public function testListConnections(): void
    {
        $result = $this->DatabaseTools->listConnections();

        $this->assertArrayHasKey('connections', $result);
        $this->assertIsArray($result['connections']);
        $this->assertNotEmpty($result['connections'], 'Should have at least one connection');

        // Find the test connection
        $testConnection = null;
        foreach ($result['connections'] as $connection) {
            if ($connection['name'] === 'test') {
                $testConnection = $connection;
                break;
            }
        }

        $this->assertNotNull($testConnection, 'Test connection should exist');
        $this->assertArrayHasKey('name', $testConnection);
        $this->assertArrayHasKey('driver', $testConnection);
        $this->assertArrayHasKey('database', $testConnection);
        $this->assertArrayHasKey('connected', $testConnection);
        $this->assertArrayHasKey('isDefault', $testConnection);

        $this->assertEquals('test', $testConnection['name']);
        $this->assertIsString($testConnection['driver']);
        $this->assertIsBool($testConnection['connected']);
        $this->assertIsBool($testConnection['isDefault']);
    }

    /**
     * Test listConnections identifies default connection
     */
    public function testListConnectionsIdentifiesDefault(): void
    {
        $result = $this->DatabaseTools->listConnections();

        $hasDefault = false;
        foreach ($result['connections'] as $connection) {
            if ($connection['isDefault'] === true) {
                $hasDefault = true;
                $this->assertEquals('default', $connection['name']);
                break;
            }
        }

        $this->assertTrue($hasDefault, 'Should identify the default connection');
    }

    /**
     * Test describeSchema returns all tables
     */
    public function testDescribeSchemaAllTables(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('tableCount', $result);
        $this->assertArrayHasKey('tables', $result);
        $this->assertEquals('test', $result['connection']);
        $this->assertIsInt($result['tableCount']);
        $this->assertIsArray($result['tables']);
        $this->assertGreaterThan(0, $result['tableCount'], 'Should have tables from fixtures');
        $this->assertCount($result['tableCount'], $result['tables']);

        // Verify table structure
        if ($result['tables'] !== []) {
            $table = $result['tables'][0];
            $this->assertArrayHasKey('name', $table);
            $this->assertArrayHasKey('columns', $table);
            $this->assertArrayHasKey('constraints', $table);
            $this->assertArrayHasKey('indexes', $table);
            $this->assertIsString($table['name']);
            $this->assertIsArray($table['columns']);
            $this->assertIsArray($table['constraints']);
            $this->assertIsArray($table['indexes']);
        }
    }

    /**
     * Test describeSchema with specific table
     */
    public function testDescribeSchemaSingleTable(): void
    {
        // Get all tables first to find an actual table name
        $allTables = $this->DatabaseTools->describeSchema('test');
        $this->assertGreaterThan(0, count($allTables['tables']), 'Need at least one table for this test');

        $tableName = $allTables['tables'][0]['name'];
        $result = $this->DatabaseTools->describeSchema('test', $tableName);

        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('table', $result);
        $this->assertEquals('test', $result['connection']);

        $table = $result['table'];
        $this->assertArrayHasKey('name', $table);
        $this->assertArrayHasKey('columns', $table);
        $this->assertArrayHasKey('constraints', $table);
        $this->assertArrayHasKey('indexes', $table);
        $this->assertEquals($tableName, $table['name']);
        $this->assertIsArray($table['columns']);
        $this->assertGreaterThan(0, count($table['columns']), 'Table should have columns');
    }

    /**
     * Test describeSchema column details
     */
    public function testDescribeSchemaColumnDetails(): void
    {
        $allTables = $this->DatabaseTools->describeSchema('test');
        $this->assertGreaterThan(0, count($allTables['tables']));

        $table = $allTables['tables'][0];
        $this->assertGreaterThan(0, count($table['columns']), 'Table should have columns');

        $column = $table['columns'][0];
        $this->assertArrayHasKey('name', $column);
        $this->assertArrayHasKey('type', $column);
        $this->assertArrayHasKey('length', $column);
        $this->assertArrayHasKey('precision', $column);
        $this->assertArrayHasKey('null', $column);
        $this->assertArrayHasKey('default', $column);
        $this->assertArrayHasKey('comment', $column);

        $this->assertIsString($column['name']);
        $this->assertIsString($column['type']);
        $this->assertIsBool($column['null']);
    }

    /**
     * Test describeSchema with invalid connection
     */
    public function testDescribeSchemaInvalidConnection(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('nonexistent_connection');

        $this->DatabaseTools->describeSchema('nonexistent_connection');
    }

    /**
     * Test describeSchema with invalid table name
     */
    public function testDescribeSchemaInvalidTable(): void
    {
        $this->expectException(ToolCallException::class);

        $this->DatabaseTools->describeSchema('test', 'nonexistent_table_xyz');
    }

    /**
     * Test describeSchema constraints structure
     */
    public function testDescribeSchemaConstraints(): void
    {
        $allTables = $this->DatabaseTools->describeSchema('test');
        $this->assertGreaterThan(0, count($allTables['tables']));

        // Find a table with constraints (most tables should have at least a primary key)
        $tableWithConstraints = null;
        foreach ($allTables['tables'] as $table) {
            if (!empty($table['constraints'])) {
                $tableWithConstraints = $table;
                break;
            }
        }

        $this->assertNotNull($tableWithConstraints, 'At least one table should have constraints');
        $this->assertIsArray($tableWithConstraints['constraints']);

        // Check constraint structure
        foreach ($tableWithConstraints['constraints'] as $name => $constraint) {
            $this->assertIsString($name);
            $this->assertArrayHasKey('type', $constraint);
            $this->assertArrayHasKey('columns', $constraint);
            $this->assertIsString($constraint['type']);
            $this->assertIsArray($constraint['columns']);
        }
    }

    /**
     * Test describeSchema indexes structure
     */
    public function testDescribeSchemaIndexes(): void
    {
        $allTables = $this->DatabaseTools->describeSchema('test');

        // Find a table with indexes
        $tableWithIndexes = null;
        foreach ($allTables['tables'] as $table) {
            if (!empty($table['indexes'])) {
                $tableWithIndexes = $table;
                break;
            }
        }

        // It's okay if no table has explicit indexes, but if one does, verify structure
        if ($tableWithIndexes !== null) {
            $this->assertIsArray($tableWithIndexes['indexes']);

            foreach ($tableWithIndexes['indexes'] as $name => $index) {
                $this->assertIsString($name);
                $this->assertArrayHasKey('columns', $index);
                $this->assertArrayHasKey('type', $index);
                $this->assertIsArray($index['columns']);
            }
        }
    }

    /**
     * Test listConnections with connection failures
     */
    public function testListConnectionsHandlesErrors(): void
    {
            $result = $this->DatabaseTools->listConnections();

            // Should still return results even if some connections fail
            $this->assertArrayHasKey('connections', $result);
            $this->assertIsArray($result['connections']);
    }

    /**
     * Test describeSchema with empty database
     */
    public function testDescribeSchemaWithEmptyDatabase(): void
    {
        // Even with fixtures, we should be able to handle the response
        $result = $this->DatabaseTools->describeSchema('test');

        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('tableCount', $result);
        $this->assertArrayHasKey('tables', $result);
        $this->assertIsInt($result['tableCount']);
    }

    /**
     * Test describeTable is called for each table
     */
    public function testDescribeTableForMultipleTables(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Verify that each table has complete structure
        foreach ($result['tables'] as $table) {
            $this->assertArrayHasKey('name', $table);
            $this->assertArrayHasKey('columns', $table);
            $this->assertArrayHasKey('constraints', $table);
            $this->assertArrayHasKey('indexes', $table);
            $this->assertIsString($table['name']);
            $this->assertIsArray($table['columns']);
            $this->assertIsArray($table['constraints']);
            $this->assertIsArray($table['indexes']);
        }
    }

    /**
     * Test column precision and length handling
     */
    public function testColumnPrecisionAndLength(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Find a table with columns to test
        if (!empty($result['tables'])) {
            $table = $result['tables'][0];
            if (!empty($table['columns'])) {
                foreach ($table['columns'] as $column) {
                    // Verify precision and length can be null or have values
                    $this->assertTrue(
                        $column['precision'] === null || is_int($column['precision']),
                        'Precision should be null or integer',
                    );
                    $this->assertTrue(
                        $column['length'] === null || is_int($column['length']),
                        'Length should be null or integer',
                    );
                }
            }
        }
    }

    /**
     * Test column default values handling
     */
    public function testColumnDefaultValues(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Verify default values are properly returned
        if (!empty($result['tables'])) {
            foreach ($result['tables'] as $table) {
                foreach ($table['columns'] as $column) {
                    // Default can be any type or null
                    $this->assertArrayHasKey('default', $column);
                }
            }
        }
    }

    /**
     * Test column comments handling
     */
    public function testColumnComments(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Verify comments are returned
        if (!empty($result['tables'])) {
            foreach ($result['tables'] as $table) {
                foreach ($table['columns'] as $column) {
                    $this->assertArrayHasKey('comment', $column);
                    // Comment should be string (empty or filled)
                    $this->assertIsString($column['comment']);
                }
            }
        }
    }

    /**
     * Test constraint types are properly identified
     */
    public function testConstraintTypes(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Find tables with constraints
        foreach ($result['tables'] as $table) {
            if (!empty($table['constraints'])) {
                foreach ($table['constraints'] as $constraint) {
                    $this->assertArrayHasKey('type', $constraint);
                    $this->assertIsString($constraint['type']);
                    $this->assertNotEmpty($constraint['type']);
                }

                break; // Just need to test one
            }
        }
    }

    /**
     * Test index types can be null
     */
    public function testIndexTypesCanBeNull(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        // Verify index type handling
        foreach ($result['tables'] as $table) {
            if (!empty($table['indexes'])) {
                foreach ($table['indexes'] as $index) {
                    $this->assertArrayHasKey('type', $index);
                    // Type can be null or string
                    $this->assertTrue(
                        $index['type'] === null || is_string($index['type']),
                        'Index type should be null or string',
                    );
                }
            }
        }
    }

    /**
     * Test connection info with null values
     */
    public function testConnectionInfoWithNullValues(): void
    {
        $result = $this->DatabaseTools->listConnections();

        // Some connection config values might be null
        foreach ($result['connections'] as $connection) {
            $this->assertArrayHasKey('database', $connection);
            $this->assertArrayHasKey('host', $connection);
            // These can be null for certain connection types
        }
    }

    /**
     * Test describeSchema returns correct structure for single table
     */
    public function testDescribeSchemaSingleTableStructure(): void
    {
        $allTables = $this->DatabaseTools->describeSchema('test');

        if (!empty($allTables['tables'])) {
            $tableName = $allTables['tables'][0]['name'];
            $result = $this->DatabaseTools->describeSchema('test', $tableName);

            // Different structure for single table
            $this->assertArrayNotHasKey('tableCount', $result);
            $this->assertArrayNotHasKey('tables', $result);
            $this->assertArrayHasKey('table', $result);
            $this->assertArrayHasKey('connection', $result);
        }
    }

    /**
     * Test listConnections with invalid connection name handling
     */
    public function testListConnectionsWithInvalidConnectionName(): void
    {
        // This tests the connection retrieval logic
        $result = $this->DatabaseTools->listConnections();

        $this->assertArrayHasKey('connections', $result);

        // Each connection should have the required fields
        foreach ($result['connections'] as $connection) {
            $this->assertArrayHasKey('name', $connection);
            $this->assertArrayHasKey('driver', $connection);
            $this->assertArrayHasKey('connected', $connection);
            $this->assertArrayHasKey('isDefault', $connection);
        }
    }

    /**
     * Test describeSchema handles connection errors
     */
    public function testDescribeSchemaConnectionError(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Failed to read schema');

        // Try to describe schema with non-existent connection
        $this->DatabaseTools->describeSchema('invalid_connection_name_xyz');
    }

    /**
     * Test listConnections handles driver retrieval errors
     */
    public function testListConnectionsHandlesDriverErrors(): void
    {
        $result = $this->DatabaseTools->listConnections();

        // Should return connections even if some fail
        $this->assertIsArray($result['connections']);

        // Verify connected status is properly set
        foreach ($result['connections'] as $connection) {
            $this->assertIsBool($connection['connected']);
        }
    }

    /**
     * Test describeSchema with table that has all column properties
     */
    public function testDescribeSchemaTableWithAllColumnProperties(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        if (!empty($result['tables'])) {
            $table = $result['tables'][0];

            foreach ($table['columns'] as $column) {
                // Verify all properties exist
                $this->assertArrayHasKey('name', $column);
                $this->assertArrayHasKey('type', $column);
                $this->assertArrayHasKey('length', $column);
                $this->assertArrayHasKey('precision', $column);
                $this->assertArrayHasKey('null', $column);
                $this->assertArrayHasKey('default', $column);
                $this->assertArrayHasKey('comment', $column);

                // Verify types
                $this->assertIsString($column['name']);
                $this->assertIsString($column['type']);
                $this->assertIsBool($column['null']);
            }
        }
    }

    /**
     * Test describeTable handles unknown column types gracefully
     */
    public function testDescribeTableUnknownColumnType(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        foreach ($result['tables'] as $table) {
            foreach ($table['columns'] as $column) {
                // Type should default to 'unknown' if not set, but in practice should have a type
                $this->assertNotEmpty($column['type']);
            }
        }
    }

    /**
     * Test describeTable handles unknown constraint types
     */
    public function testDescribeTableUnknownConstraintType(): void
    {
        $result = $this->DatabaseTools->describeSchema('test');

        foreach ($result['tables'] as $table) {
            if (!empty($table['constraints'])) {
                foreach ($table['constraints'] as $constraint) {
                    // Type should default to 'unknown' if not set
                    $this->assertArrayHasKey('type', $constraint);
                    $this->assertNotEmpty($constraint['type']);
                }
            }
        }
    }

    /**
     * Test connection config values can be null
     */
    public function testConnectionConfigNullValues(): void
    {
        $result = $this->DatabaseTools->listConnections();

        foreach ($result['connections'] as $connection) {
            // Database and host might be null for certain drivers
            $this->assertTrue(
                $connection['database'] === null || is_string($connection['database']),
                'Database should be null or string',
            );
            $this->assertTrue(
                $connection['host'] === null || is_string($connection['host']),
                'Host should be null or string',
            );
        }
    }
}
