<?php
declare(strict_types=1);

namespace Synapse\Server;

use Cake\Core\Configure;
use Crustum\Mcp\Server;
use Crustum\Mcp\Server\Prompt;
use Crustum\Mcp\Server\Resource;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Support\ContainerRegistry;
use InvalidArgumentException;
use Synapse\Prompts\CodeReviewerPrompt;
use Synapse\Prompts\DatabaseExplorerPrompt;
use Synapse\Prompts\DebugHelperPrompt;
use Synapse\Prompts\DocumentationExpertPrompt;
use Synapse\Prompts\FeatureBuilderPrompt;
use Synapse\Prompts\MigrationGuidePrompt;
use Synapse\Prompts\OrmQueryHelperPrompt;
use Synapse\Prompts\PerformanceAnalyzerPrompt;
use Synapse\Prompts\QualityAssurancePrompt;
use Synapse\Prompts\TestingAssistantPrompt;
use Synapse\Prompts\TinkerWorkshopPrompt;
use Synapse\Resources\DocumentationContentResource;
use Synapse\Resources\DocumentationSearchResource;
use Synapse\SynapsePlugin;
use Synapse\Tools\CacheClearTool;
use Synapse\Tools\CacheConfigsTool;
use Synapse\Tools\CacheDeleteTool;
use Synapse\Tools\CacheReadTool;
use Synapse\Tools\CacheWriteTool;
use Synapse\Tools\ConfigReadTool;
use Synapse\Tools\DatabaseConnectionsTool;
use Synapse\Tools\DatabaseSchemaTool;
use Synapse\Tools\DebugStatusTool;
use Synapse\Tools\DetectRouteCollisionsTool;
use Synapse\Tools\DocsStatsTool;
use Synapse\Tools\GetCommandInfoTool;
use Synapse\Tools\GetDocTool;
use Synapse\Tools\GetRouteTool;
use Synapse\Tools\ListCommandsTool;
use Synapse\Tools\ListEnvVarsTool;
use Synapse\Tools\ListRoutesTool;
use Synapse\Tools\LogListTool;
use Synapse\Tools\LogReadTool;
use Synapse\Tools\MatchUrlTool;
use Synapse\Tools\OrmDescribeTool;
use Synapse\Tools\OrmFindTool;
use Synapse\Tools\RunCommandTool;
use Synapse\Tools\SearchDocsTool;
use Synapse\Tools\SystemInfoTool;
use Synapse\Tools\TinkerTool;
use Throwable;

/**
 * Explicit catalog of CakePHP development tools, prompts and documentation.
 */
class SynapseServer extends Server
{
    public const DEFAULT_SERVER_NAME = 'Synapse MCP Server';

    public const DEFAULT_INSTRUCTIONS = 'Synapse exposes CakePHP inspection and development capabilities over MCP. ' .
        'Start with read-only discovery tools such as system_info, list_commands, search_docs, ' .
        'database_schema, orm_describe, and list_routes. Use get_doc after search_docs when full ' .
        'documentation is needed. Use tinker and run_command only when necessary and with explicit ' .
        'approval for side effects.';

    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Tool>|\Crustum\Mcp\Server\Tool>
     */
    protected array $tools = [
        SystemInfoTool::class,
        ConfigReadTool::class,
        DebugStatusTool::class,
        ListEnvVarsTool::class,
        DatabaseConnectionsTool::class,
        DatabaseSchemaTool::class,
        ListRoutesTool::class,
        GetRouteTool::class,
        MatchUrlTool::class,
        DetectRouteCollisionsTool::class,
        CacheConfigsTool::class,
        CacheReadTool::class,
        CacheWriteTool::class,
        CacheDeleteTool::class,
        CacheClearTool::class,
        ListCommandsTool::class,
        GetCommandInfoTool::class,
        RunCommandTool::class,
        LogListTool::class,
        LogReadTool::class,
        OrmDescribeTool::class,
        OrmFindTool::class,
        TinkerTool::class,
        SearchDocsTool::class,
        DocsStatsTool::class,
        GetDocTool::class,
    ];

    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Prompt>|\Crustum\Mcp\Server\Prompt>
     */
    protected array $prompts = [
        CodeReviewerPrompt::class,
        DatabaseExplorerPrompt::class,
        DebugHelperPrompt::class,
        DocumentationExpertPrompt::class,
        FeatureBuilderPrompt::class,
        MigrationGuidePrompt::class,
        OrmQueryHelperPrompt::class,
        PerformanceAnalyzerPrompt::class,
        TestingAssistantPrompt::class,
        TinkerWorkshopPrompt::class,
        QualityAssurancePrompt::class,
    ];

    /**
     * @var array<int, class-string<\Crustum\Mcp\Server\Resource>|\Crustum\Mcp\Server\Resource>
     */
    protected array $resources = [
        DocumentationSearchResource::class,
        DocumentationContentResource::class,
    ];

    /**
     * @inheritDoc
     */
    protected function boot(): void
    {
        $this->name = Configure::read('Synapse.serverInfo.name', self::DEFAULT_SERVER_NAME);
        $this->version = Configure::read('Synapse.serverInfo.version', SynapsePlugin::VERSION);
        $this->instructions = Configure::read('Synapse.instructions', self::DEFAULT_INSTRUCTIONS);
        $this->tools = $this->registeredCapabilities('tools', $this->tools, Tool::class);
        $this->prompts = $this->registeredCapabilities('prompts', $this->prompts, Prompt::class);
        $this->resources = $this->registeredCapabilities('resources', $this->resources, Resource::class);
    }

    /**
     * Resolve the explicit catalog through CakePHP's container.
     *
     * @template T of \Crustum\Mcp\Server\Primitive
     * @param string $kind Configuration category
     * @param array<int, class-string<T>|T> $builtins Built-in capabilities
     * @param class-string<T> $base Required Crustum primitive type
     * @return array<int, T>
     */
    private function registeredCapabilities(string $kind, array $builtins, string $base): array
    {
        $additional = Configure::read('Synapse.capabilities.' . $kind, []);
        if (!is_array($additional) || !array_is_list($additional)) {
            throw new InvalidArgumentException(
                'Synapse.capabilities.' . $kind . ' must be a list of capability classes.',
            );
        }

        $container = ContainerRegistry::getInstance();
        $resolved = [];
        foreach (array_merge($builtins, $additional) as $class) {
            if (!is_string($class) || !is_a($class, $base, true)) {
                throw new InvalidArgumentException(
                    'Synapse.capabilities.' . $kind . ' must contain subclasses of ' . $base,
                );
            }

            try {
                $primitive = $container->has($class) ? $container->get($class) : new $class();
            } catch (Throwable $exception) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Cannot construct capability "%s". Register its dependencies in the application container.',
                        $class,
                    ),
                    0,
                    $exception,
                );
            }

            if (!$primitive instanceof $base) {
                throw new InvalidArgumentException(
                    'The container must resolve ' . $class . ' to an instance of ' . $base,
                );
            }

            $name = $primitive->name();
            if (isset($resolved[$name])) {
                throw new InvalidArgumentException(sprintf('Duplicate %s capability name "%s".', $kind, $name));
            }

            $resolved[$name] = $primitive;
        }

        return array_values($resolved);
    }
}
