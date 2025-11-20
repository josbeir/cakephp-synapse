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
}
