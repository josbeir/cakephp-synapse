<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\Tools\ModelTools;

/**
 * ModelTools Test Case
 *
 * Tests for ORM introspection and query MCP tools.
 * Uses the Articles and Users fixtures on the 'test' connection.
 */
class ModelToolsTest extends TestCase
{
    private ModelTools $modelTools;

    /**
     * @var array<string> fixtures to load for each test
     */
    protected array $fixtures = [
        'plugin.Synapse.Articles',
        'plugin.Synapse.Users',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->modelTools = new ModelTools();
    }

    // -------------------------------------------------------------------------
    // orm_describe
    // -------------------------------------------------------------------------

    /**
     * Test orm_describe returns the expected top-level structure.
     */
    public function testOrmDescribeReturnsExpectedStructure(): void
    {
        $result = $this->modelTools->ormDescribe('Articles', 'test');

        $this->assertArrayHasKey('alias', $result);
        $this->assertArrayHasKey('table', $result);
        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('primaryKey', $result);
        $this->assertArrayHasKey('displayField', $result);
        $this->assertArrayHasKey('entityClass', $result);
        $this->assertArrayHasKey('associations', $result);
        $this->assertArrayHasKey('behaviors', $result);
    }

    /**
     * Test orm_describe returns correct alias and table name.
     */
    public function testOrmDescribeAliasAndTable(): void
    {
        $result = $this->modelTools->ormDescribe('Articles', 'test');

        $this->assertSame('Articles', $result['alias']);
        $this->assertSame('articles', $result['table']);
    }

    /**
     * Test orm_describe reports the correct connection name.
     */
    public function testOrmDescribeConnectionName(): void
    {
        $result = $this->modelTools->ormDescribe('Articles', 'test');

        $this->assertSame('test', $result['connection']);
    }

    /**
     * Test orm_describe associations is an array.
     */
    public function testOrmDescribeAssociationsIsArray(): void
    {
        $result = $this->modelTools->ormDescribe('Articles', 'test');

        $this->assertIsArray($result['associations']);
        $this->assertIsArray($result['behaviors']);
    }

    // -------------------------------------------------------------------------
    // orm_find
    // -------------------------------------------------------------------------

    /**
     * Test orm_find with type=all returns records from fixtures.
     */
    public function testOrmFindAllReturnsRecords(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test');

        $this->assertArrayHasKey('alias', $result);
        $this->assertArrayHasKey('records', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertSame('Articles', $result['alias']);
        $this->assertSame(3, $result['count']);
        $this->assertCount(3, $result['records']);
    }

    /**
     * Test orm_find records have the expected field keys.
     */
    public function testOrmFindRecordsHaveExpectedFields(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test');

        $first = $result['records'][0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('title', $first);
        $this->assertArrayHasKey('body', $first);
    }

    /**
     * Test orm_find with type=first returns a single record.
     */
    public function testOrmFindFirstReturnsOneRecord(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test', 'first');

        $this->assertArrayHasKey('alias', $result);
        $this->assertArrayHasKey('record', $result);
        $this->assertIsArray($result['record']);
        $this->assertArrayHasKey('id', $result['record']);
        $this->assertArrayHasKey('title', $result['record']);
    }

    /**
     * Test orm_find with type=count returns an integer count.
     */
    public function testOrmFindCountReturnsInteger(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test', 'count');

        $this->assertArrayHasKey('count', $result);
        $this->assertSame(3, $result['count']);
    }

    /**
     * Test orm_find respects the limit parameter.
     */
    public function testOrmFindLimitIsRespected(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test', 'all', 1);

        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['records']);
    }

    /**
     * Test orm_find clamps limit < 1 up to 1.
     */
    public function testOrmFindLimitClampedToMinimum(): void
    {
        $result = $this->modelTools->ormFind('Articles', 'test', 'all', 0);

        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['records']);
    }

    /**
     * Test orm_find throws ToolCallException for an invalid find type.
     */
    public function testOrmFindInvalidTypeThrows(): void
    {
        $this->expectException(ToolCallException::class);

        $this->modelTools->ormFind('Articles', 'test', 'invalid_type');
    }
}
