<?php
declare(strict_types=1);

namespace Synapse\Tools;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Attributes\Name;
use Crustum\Mcp\Server\Tools\Annotations\IsDestructive;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;
use Synapse\Service\CacheService;

/**
 * Read a value from a CakePHP cache configuration by key
 */
#[Name('cache_read')]
#[Description('Read a value from a CakePHP cache configuration by key')]
#[IsReadOnly(true)]
#[IsDestructive(false)]
#[IsIdempotent(true)]
class CacheReadTool extends AbstractTool
{
    /**
     * @inheritDoc
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'config' => $schema->string()
                ->description("Cache configuration name (e.g. 'default')")
                ->required(),
            'key' => $schema->string()
                ->description('Cache key to read')
                ->required(),
        ];
    }

    /**
     * Invoke the capability using application services.
     *
     * @param \Crustum\Mcp\Request $request MCP arguments
     * @param \Synapse\Service\CacheService $service Application service
     */
    public function handle(Request $request, CacheService $service): Response
    {
        $arguments = $this->validatedArguments($request);

        return $this->respond(fn(): mixed => $service->cacheRead(
            $arguments['config'],
            $arguments['key'],
        ));
    }
}
