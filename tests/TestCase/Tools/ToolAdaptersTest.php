<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Synapse\Exception\CapabilityException;
use Synapse\Server\SynapseServer;
use Synapse\Service\CacheService;
use Synapse\Service\CommandService;
use Synapse\Service\DatabaseService;
use Synapse\Service\DocumentationService;
use Synapse\Service\LogService;
use Synapse\Service\ModelService;
use Synapse\Service\RouteService;
use Synapse\Service\SystemService;
use Synapse\Service\TinkerService;
use Synapse\Test\TestCase\McpTestCase;
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

/**
 * Exercises every tool through Crustum's request invocation and response serializer.
 */
class ToolAdaptersTest extends McpTestCase
{
    /**
     * @return array<string, array{
     *     class-string<\Crustum\Mcp\Server\Tool>, class-string, non-empty-string, array<string, mixed>
     * }>
     */
    public static function toolProvider(): array
    {
        return [
            'system_info' => [
                SystemInfoTool::class,
                SystemService::class,
                'getSystemInfo',
                [],
            ],
            'config_read' => [
                ConfigReadTool::class,
                SystemService::class,
                'readConfig',
                [
                    'key' => 'App.name',
                ],
            ],
            'debug_status' => [
                DebugStatusTool::class,
                SystemService::class,
                'getDebugStatus',
                [],
            ],
            'list_env_vars' => [
                ListEnvVarsTool::class,
                SystemService::class,
                'listEnvVars',
                [],
            ],
            'database_connections' => [
                DatabaseConnectionsTool::class,
                DatabaseService::class,
                'listConnections',
                [],
            ],
            'database_schema' => [
                DatabaseSchemaTool::class,
                DatabaseService::class,
                'describeSchema',
                [
                    'connection' => 'default',
                    'table' => 'Articles',
                ],
            ],
            'list_routes' => [
                ListRoutesTool::class,
                RouteService::class,
                'listRoutes',
                [
                    'sort' => true,
                    'method' => 'GET',
                    'plugin' => 'Synapse',
                    'prefix' => 'Admin',
                    'controller' => 'Articles',
                    'withMiddlewares' => true,
                ],
            ],
            'get_route' => [
                GetRouteTool::class,
                RouteService::class,
                'getRoute',
                [
                    'name' => 'example',
                ],
            ],
            'match_url' => [
                MatchUrlTool::class,
                RouteService::class,
                'matchUrl',
                [
                    'url' => '/articles',
                    'method' => 'GET',
                ],
            ],
            'detect_route_collisions' => [
                DetectRouteCollisionsTool::class,
                RouteService::class,
                'detectRouteCollisions',
                [],
            ],
            'cache_configs' => [
                CacheConfigsTool::class,
                CacheService::class,
                'listConfigs',
                [],
            ],
            'cache_read' => [
                CacheReadTool::class,
                CacheService::class,
                'cacheRead',
                [
                    'config' => 'default',
                    'key' => 'App.name',
                ],
            ],
            'cache_write' => [
                CacheWriteTool::class,
                CacheService::class,
                'cacheWrite',
                [
                    'config' => 'default',
                    'key' => 'App.name',
                    'value' => 'example',
                ],
            ],
            'cache_delete' => [
                CacheDeleteTool::class,
                CacheService::class,
                'cacheDelete',
                [
                    'config' => 'default',
                    'key' => 'App.name',
                ],
            ],
            'cache_clear' => [
                CacheClearTool::class,
                CacheService::class,
                'cacheClear',
                [
                    'config' => 'default',
                ],
            ],
            'list_commands' => [
                ListCommandsTool::class,
                CommandService::class,
                'listCommands',
                [
                    'plugin' => 'Synapse',
                    'namespace' => 'App',
                    'sort' => true,
                ],
            ],
            'get_command_info' => [
                GetCommandInfoTool::class,
                CommandService::class,
                'getCommandInfo',
                [
                    'name' => 'example',
                ],
            ],
            'run_command' => [
                RunCommandTool::class,
                CommandService::class,
                'runCommand',
                [
                    'name' => 'example',
                    'args' => '--help',
                    'timeout' => 42,
                ],
            ],
            'log_list' => [
                LogListTool::class,
                LogService::class,
                'listLogs',
                [],
            ],
            'log_read' => [
                LogReadTool::class,
                LogService::class,
                'readLog',
                [
                    'file' => 'debug.log',
                    'lines' => 12,
                    'level' => 'error',
                ],
            ],
            'orm_describe' => [
                OrmDescribeTool::class,
                ModelService::class,
                'ormDescribe',
                [
                    'alias' => 'Articles',
                    'connection' => 'default',
                ],
            ],
            'orm_find' => [
                OrmFindTool::class,
                ModelService::class,
                'ormFind',
                [
                    'alias' => 'Articles',
                    'connection' => 'default',
                    'type' => 'all',
                    'limit' => 5,
                ],
            ],
            'tinker' => [
                TinkerTool::class,
                TinkerService::class,
                'execute',
                [
                    'code' => 'return 1;',
                    'timeout' => 42,
                ],
            ],
            'search_docs' => [
                SearchDocsTool::class,
                DocumentationService::class,
                'searchDocs',
                [
                    'query' => 'caching',
                    'limit' => 5,
                    'fuzzy' => true,
                    'sources' => 'cakephp-5x',
                ],
            ],
            'docs_stats' => [
                DocsStatsTool::class,
                DocumentationService::class,
                'getStats',
                [],
            ],
            'get_doc' => [
                GetDocTool::class,
                DocumentationService::class,
                'getDocument',
                [
                    'docId' => 'cakephp-5x::docs/en/caching.md',
                ],
            ],
        ];
    }

    /**
     * @param class-string<\Crustum\Mcp\Server\Tool> $tool Tool class
     * @param class-string $service Service class
     * @param non-empty-string $method Expected service operation
     * @param array<string, mixed> $arguments Advertised arguments
     */
    #[DataProvider('toolProvider')]
    public function testInvocation(string $tool, string $service, string $method, array $arguments): void
    {
        $mock = $this->createMock($service);
        $mock->expects($this->once())->method($method)
            ->with(...array_values($arguments))
            ->willReturn(['result' => 'example']);
        $this->container->add($service, $mock);

        $response = SynapseServer::tool($tool, $arguments);
        $response->assertOk()->assertSee('{"result":"example"}');

        $metadata = (new $tool())->toArray();
        $this->assertNotEmpty($metadata['description'] ?? null);
        $this->assertSame(array_keys($arguments), array_keys((array)($metadata['inputSchema'] ?? [])['properties']));
        foreach (($metadata['inputSchema'] ?? [])['properties'] as $property) {
            $this->assertNotEmpty($property['description']);
        }
    }

    /**
     * Each optional default must reach the typed service unchanged.
     *
     * @param class-string<\Crustum\Mcp\Server\Tool> $tool Tool class
     * @param class-string $service Service class
     * @param non-empty-string $method Expected service operation
     * @param array<string, mixed> $arguments Valid argument values
     */
    #[DataProvider('toolProvider')]
    public function testDefaultsAndInvalidTypes(string $tool, string $service, string $method, array $arguments): void
    {
        $definition = (new $tool())->toArray()['inputSchema'] ?? [];
        $input = array_intersect_key($arguments, array_flip($definition['required'] ?? []));
        $expected = [];
        foreach ($definition['properties'] as $name => $property) {
            $expected[] = $input[$name] ?? $property['default'] ?? null;
        }

        $mock = $this->createMock($service);
        $mock->expects($this->once())->method($method)->with(...$expected)->willReturn([]);
        $this->container->add($service, $mock);
        SynapseServer::tool($tool, $input)->assertOk()->assertSee('[]');

        foreach ($definition['properties'] as $name => $property) {
            SynapseServer::tool($tool, array_replace($arguments, [$name => []]))
                ->assertHasErrors(['Argument "' . $name . '" must be']);
        }

        foreach ($definition['required'] ?? [] as $name) {
            $missing = $arguments;
            unset($missing[$name]);
            SynapseServer::tool($tool, $missing)->assertHasErrors(['Argument "' . $name . '" is required']);
        }
    }

    public function testExpectedFailureIsActionable(): void
    {
        $service = $this->createStub(CacheService::class);
        $service->method('cacheRead')->willThrowException(
            new CapabilityException('Cache config "missing" is not configured.'),
        );
        $this->container->add(CacheService::class, $service);

        SynapseServer::tool(CacheReadTool::class, ['config' => 'missing', 'key' => 'example'])
            ->assertHasErrors(['Cache config "missing" is not configured.']);
    }

    public function testUnexpectedFailureDoesNotExposeDetails(): void
    {
        $service = $this->createStub(SystemService::class);
        $service->method('readConfig')->willThrowException(new RuntimeException('sensitive implementation detail'));
        $this->container->add(SystemService::class, $service);

        SynapseServer::tool(ConfigReadTool::class, ['key' => 'App.name'])
            ->assertHasErrors(['The operation failed. Check the application logs for details.']);
    }

    public function testScalarAndNullPayloadsArePreserved(): void
    {
        foreach ([null, false, 'example', 42] as $value) {
            $service = $this->createStub(SystemService::class);
            $service->method('readConfig')->willReturn($value);
            $this->container->add(SystemService::class, $service);

            SynapseServer::tool(ConfigReadTool::class, ['key' => 'App.name'])
                ->assertOk()->assertSee(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }

    public function testInvalidEnumIsRejectedBeforeQuerying(): void
    {
        SynapseServer::tool(OrmFindTool::class, ['alias' => 'Articles', 'type' => 'invalid'])
            ->assertHasErrors(['Argument "type" must be one of']);
    }

    public function testExplicitNullIsAcceptedOnlyForNullableArguments(): void
    {
        $service = $this->createMock(DatabaseService::class);
        $service->expects($this->once())->method('describeSchema')->with('default', null)->willReturn([]);
        $this->container->add(DatabaseService::class, $service);

        SynapseServer::tool(DatabaseSchemaTool::class, ['table' => null])->assertOk();
        SynapseServer::tool(DatabaseSchemaTool::class, ['connection' => null])
            ->assertHasErrors(['Argument "connection" must be string.']);
    }

    /**
     * @return array<string, array{
     *     class-string<\Crustum\Mcp\Server\Tool>, class-string, non-empty-string, array<string, mixed>, string
     * }>
     */
    public static function nullableFilterProvider(): array
    {
        $cases = [];
        $filtersByTool = [
            'database_schema' => ['table'],
            'list_commands' => ['plugin', 'namespace'],
            'list_routes' => ['method', 'plugin', 'prefix', 'controller'],
            'log_read' => ['level'],
        ];
        foreach ($filtersByTool as $name => $filters) {
            [$tool, $service, $method, $arguments] = self::toolProvider()[$name];
            foreach ($filters as $filter) {
                $cases[$name . '.' . $filter] = [$tool, $service, $method, $arguments, $filter];
            }
        }

        return $cases;
    }

    /**
     * @param class-string<\Crustum\Mcp\Server\Tool> $tool Tool class
     * @param class-string $service Service class
     * @param non-empty-string $method Expected service operation
     * @param array<string, mixed> $arguments Valid argument values
     * @param string $filter Nullable filter name
     */
    #[DataProvider('nullableFilterProvider')]
    public function testNullableFilterValidation(
        string $tool,
        string $service,
        string $method,
        array $arguments,
        string $filter,
    ): void {
        foreach ([null, ''] as $value) {
            $input = array_replace($arguments, [$filter => $value]);
            $mock = $this->createMock($service);
            $mock->expects($this->once())->method($method)->with(...array_values($input))->willReturn([]);
            $this->container->add($service, $mock);

            SynapseServer::tool($tool, $input)->assertOk()->assertSee('[]');
        }

        $mock = $this->createMock($service);
        $mock->expects($this->never())->method($method);
        $this->container->add($service, $mock);
        foreach ([42, false, []] as $invalid) {
            SynapseServer::tool($tool, array_replace($arguments, [$filter => $invalid]))
                ->assertHasErrors(['Argument "' . $filter . '" must be string or null.']);
        }
    }
}
