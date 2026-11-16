<?php
declare(strict_types=1);

namespace Synapse\Prompts;

use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Prompts\Argument;

/**
 * Database Explorer Prompt
 *
 * Explore and understand database schema, relationships, and data.
 */
#[Name('database-explorer')]
#[Description('Explore and understand database schema, relationships, and data')]
class DatabaseExplorerPrompt extends AbstractPrompt
{
    /**
     * @inheritDoc
     */
    public function arguments(): array
    {
        return [
            new Argument('table', 'Table name to explore', true),
            new Argument('show', 'What to show (schema, data, relationships, all)', false),
        ];
    }

    /**
     * Render the advertised prompt arguments.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @return array<\Crustum\Mcp\Response>
     */
    public function handle(Request $request): array
    {
        $arguments = $this->validatedArguments($request);

        return $this->render($arguments['table'], $arguments['show'] ?? 'all');
    }

    /**
     * Explore database schema and data
     *
     * @param string $table Table name to explore
     * @param string $show What to show (schema, data, relationships, all)
     * @return array<\Crustum\Mcp\Response>
     */
    public function render(
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
            Response::text(<<<TEXT
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
TEXT),
        ];
    }
}
