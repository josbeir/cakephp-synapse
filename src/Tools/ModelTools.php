<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Cake\ORM\TableRegistry;
use Exception;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;

/**
 * Model Tools
 *
 * MCP tools for ORM introspection and querying CakePHP table instances.
 */
class ModelTools
{
    /**
     * Describe a CakePHP ORM Table instance.
     *
     * Returns ORM-level metadata for the given table alias including
     * associations, behaviors, display field, primary key, and entity class.
     *
     * @param string $alias Table alias in CamelCase (e.g. 'Articles')
     * @param string $connection Connection name to use (defaults to 'default')
     * @return array<string, mixed> ORM table description
     */
    #[McpTool(
        name: 'orm_describe',
        description: 'Describe a CakePHP ORM Table: associations, behaviors, display field, primary key, entity class',
    )]
    public function ormDescribe(string $alias, string $connection = 'default'): array
    {
        try {
            $table = TableRegistry::getTableLocator()->get($alias, ['connectionName' => $connection]);

            $associations = [];
            foreach ($table->associations() as $association) {
                $foreignKey = $association->getForeignKey();
                $associations[] = [
                    'name' => $association->getName(),
                    'type' => $association->type(),
                    'alias' => $association->getAlias(),
                    'foreignKey' => $foreignKey === false ? null : $foreignKey,
                    'className' => $association->getClassName(),
                ];
            }

            return [
                'alias' => $table->getAlias(),
                'table' => $table->getTable(),
                'connection' => $table->getConnection()->configName(),
                'primaryKey' => $table->getPrimaryKey(),
                'displayField' => $table->getDisplayField(),
                'entityClass' => $table->getEntityClass(),
                'associations' => $associations,
                'behaviors' => $table->behaviors()->loaded(),
            ];
        } catch (Exception $exception) {
            throw new ToolCallException(
                sprintf("Failed to describe table '%s': %s", $alias, $exception->getMessage()),
                $exception->getCode(),
                $exception,
            );
        }
    }

    /**
     * Run a find query on a CakePHP ORM Table.
     *
     * Executes a find of the given type against the specified table alias
     * and returns the results as plain arrays. The limit is clamped between
     * 1 and 100.
     *
     * @param string $alias Table alias in CamelCase (e.g. 'Articles')
     * @param string $connection Connection name to use (defaults to 'default')
     * @param string $type Find type: 'all', 'first', or 'count'
     * @param int $limit Maximum number of records to return (clamped 1–100)
     * @return array<string, mixed> Query results
     */
    #[McpTool(
        name: 'orm_find',
        description: "Run a find query on a CakePHP ORM Table. Type must be 'all', 'first', or 'count'.",
    )]
    public function ormFind(
        string $alias,
        string $connection = 'default',
        string $type = 'all',
        int $limit = 10,
    ): array {
        $validTypes = ['all', 'first', 'count'];
        if (!in_array($type, $validTypes, true)) {
            throw new ToolCallException(sprintf(
                "Invalid find type '%s'. Valid types: %s",
                $type,
                implode(', ', $validTypes),
            ));
        }

        $limit = max(1, min(100, $limit));

        try {
            $table = TableRegistry::getTableLocator()->get($alias, ['connectionName' => $connection]);

            if ($type === 'count') {
                return [
                    'alias' => $alias,
                    'count' => $table->find()->count(),
                ];
            }

            $query = $table->find()->limit($limit)->enableHydration(false);

            if ($type === 'first') {
                /** @var array<string, mixed>|null $record */
                $record = $query->first();

                return [
                    'alias' => $alias,
                    'record' => $record,
                ];
            }

            /** @var array<int, array<string, mixed>> $records */
            $records = $query->all()->toList();

            return [
                'alias' => $alias,
                'count' => count($records),
                'records' => $records,
            ];
        } catch (ToolCallException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new ToolCallException(
                sprintf("Failed to find records for '%s': %s", $alias, $e->getMessage()),
                $e->getCode(),
                $e,
            );
        }
    }
}
