<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * ORM Query Helper Prompt
 *
 * Build complex ORM queries with guidance.
 */
class OrmQueryHelperPrompt extends AbstractPrompt
{
    /**
     * Build complex ORM queries with guidance
     *
     * @param string $queryGoal What you want to query (describe in plain English)
     * @param string $tables Tables involved (comma-separated)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'orm-query-helper',
        description: 'Build complex ORM queries with guidance',
    )]
    public function handle(
        string $queryGoal,
        string $tables = '',
    ): array {
        $this->validateNonEmptyParameter($queryGoal, 'queryGoal', 'orm-query-helper');

        $tablesHint = $tables !== '' && $tables !== '0' ? '
Tables involved: ' . $tables : '';

        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need to build a CakePHP {$this->cakephpVersion} ORM query for: {$queryGoal}{$tablesHint}

Please:
1. Search CakePHP {$this->cakephpVersion} ORM documentation for relevant query methods
2. Read documentation on:
   - Query builder methods (find, where, contain, matching, etc.)
   - Association types and strategies
   - Aggregations and grouping
   - Subqueries and complex conditions
3. Build the query step-by-step:
   - Start with the base find() or table selection
   - Add conditions (where, orWhere, andWhere)
   - Include associations (contain, matching, innerJoinWith, leftJoinWith)
   - Apply sorting (orderBy, orderAsc, orderDesc)
   - Add limiting and pagination (limit, offset, page)
   - Include any aggregations, grouping, or having clauses
4. Show the complete, production-ready query code
5. Explain each part of the query and why it's needed
6. Suggest optimizations:
   - Eager loading vs lazy loading
   - Index recommendations
   - Query result caching
7. Provide a tinker command to test the query:
   ```php
   \$table = \$this->fetchTable('TableName');
   \$query = \$table->find()
       // ... your query builder methods here
       ->limit(10);
   \$results = \$query->toArray();
   var_export(\$results);
   ```
8. Show the equivalent SQL for understanding (use `\$query->sql()`)
9. Add proper error handling and type safety

Make it production-ready with proper type hints and null handling.
TEXT,
                ),
            ),
        ];
    }
}
