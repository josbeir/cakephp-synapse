<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Schema\Content\PromptMessage;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\Role;

/**
 * Database Explorer Prompt
 *
 * Explore and understand database schema, relationships, and data.
 */
class DatabaseExplorerPrompt extends AbstractPrompt
{
    /**
     * Explore database schema and data
     *
     * @param string $table Table name to explore
     * @param string $show What to show (schema, data, relationships, all)
     * @return array<\Mcp\Schema\Content\PromptMessage>
     */
    #[McpPrompt(
        name: 'database-explorer',
        description: 'Explore and understand database schema, relationships, and data',
    )]
    public function handle(
        string $table,
        string $show = 'all',
    ): array {
        $this->validateNonEmptyParameter($table, 'table', 'database-explorer');
        $this->validateEnumParameter(
            $show,
            ['schema', 'data', 'relationships', 'all'],
            'show',
            'database-explorer',
        );

        return [
            new PromptMessage(
                role: Role::User,
                content: new TextContent(
                    text: <<<TEXT
I need to understand the '{$table}' table in my CakePHP {$this->cakephpVersion} application.

Show me: {$show}

Please:
1. Use the tinker tool to inspect the table structure:
   `\$table = \$this->fetchTable('{$table}'); var_export(\$table->getSchema()->columns());`

2. Show the column definitions (types, lengths, nullable, defaults)

3. If showing relationships, inspect associations:
   `var_export(\$table->associations()->keys());`

4. If showing data, fetch sample records:
   `\$this->fetchTable('{$table}')->find()->limit(5)->toArray();`

5. Provide guidance on:
   - How to query this table using CakePHP {$this->cakephpVersion} ORM
   - Common patterns for this table structure
   - Any validation rules that should be applied
   - Suggested indexes or optimizations

Format the output in a clear, readable way.
TEXT,
                ),
            ),
        ];
    }
}
