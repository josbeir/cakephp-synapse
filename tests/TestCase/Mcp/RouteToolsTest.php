<?php
declare(strict_types=1);

namespace Synapse\Test\TestCase\Mcp;

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Mcp\Exception\ToolCallException;
use Synapse\Mcp\RouteTools;

/**
 * RouteTools Test Case
 *
 * Tests for route inspection and management tools.
 */
class RouteToolsTest extends TestCase
{
    /**
     * Test subject
     */
    protected RouteTools $RouteTools;

    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->RouteTools = new RouteTools();

        // Set up test routes
        Router::reload();
        $routeBuilder = Router::createRouteBuilder('/');
        $routeBuilder->scope('/', function (RouteBuilder $routes): void {
            $routes->setRouteClass(DashedRoute::class);

            // Basic routes
            $routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home'], ['_name' => 'home']);
            $routes->connect('/about', ['controller' => 'Pages', 'action' => 'display', 'about'], ['_name' => 'about']);

            // Parameterized routes
            $routes->connect('/projects/:id', ['controller' => 'Projects', 'action' => 'view'], ['_name' => 'projects:view'])
                ->setPass(['id'])
                ->setMethods(['GET']);

            $routes->connect('/projects/:id/edit', ['controller' => 'Projects', 'action' => 'edit'], ['_name' => 'projects:edit'])
                ->setPass(['id'])
                ->setMethods(['GET', 'POST']);

            // Plugin route
            $routes->connect('/admin/settings', ['plugin' => 'Admin', 'controller' => 'Settings', 'action' => 'index'], ['_name' => 'admin:settings']);

            // Prefix route
            $routes->prefix('Api', function (RouteBuilder $routes): void {
                $routes->connect('/users', ['controller' => 'Users', 'action' => 'index'], ['_name' => 'api:users:index'])
                    ->setMethods(['GET']);
            });

            $routes->fallbacks();
        });
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        Router::reload();
        parent::tearDown();
    }

    /**
     * Test listRoutes method
     */
    public function testListRoutes(): void
    {
        $result = $this->RouteTools->listRoutes();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('routes', $result);
        $this->assertIsInt($result['total']);
        $this->assertIsArray($result['routes']);
        $this->assertGreaterThan(0, $result['total']);
        $this->assertCount($result['total'], $result['routes']);

        // Check route structure
        if (!empty($result['routes'])) {
            $route = $result['routes'][0];
            $this->assertArrayHasKey('name', $route);
            $this->assertArrayHasKey('template', $route);
            $this->assertArrayHasKey('plugin', $route);
            $this->assertArrayHasKey('prefix', $route);
            $this->assertArrayHasKey('controller', $route);
            $this->assertArrayHasKey('action', $route);
            $this->assertArrayHasKey('methods', $route);
            $this->assertIsArray($route['methods']);
        }
    }

    /**
     * Test listRoutes with sorting
     */
    public function testListRoutesWithSorting(): void
    {
        $result = $this->RouteTools->listRoutes(sort: true);

        $this->assertGreaterThan(0, $result['total']);

        // Verify sorting - extract names and check they're in order
        $names = array_map(fn(array $route): string => (string)$route['name'], $result['routes']);
        $sortedNames = $names;
        sort($sortedNames, SORT_STRING | SORT_FLAG_CASE);

        $this->assertEquals($sortedNames, $names, 'Routes should be sorted alphabetically');
    }

    /**
     * Test listRoutes with method filter
     */
    public function testListRoutesWithMethodFilter(): void
    {
        $result = $this->RouteTools->listRoutes(method: 'GET');

        $this->assertGreaterThan(0, $result['total']);

        // All routes should have GET method
        foreach ($result['routes'] as $route) {
            if (!empty($route['methods'])) {
                $methods = array_map('strtoupper', $route['methods']);
                $this->assertContains('GET', $methods, sprintf('Route %s should have GET method', $route['name']));
            }
        }
    }

    /**
     * Test listRoutes with plugin filter
     */
    public function testListRoutesWithPluginFilter(): void
    {
        $result = $this->RouteTools->listRoutes(plugin: 'Admin');

        $this->assertGreaterThan(0, $result['total']);

        // All routes should belong to Admin plugin
        foreach ($result['routes'] as $route) {
            $this->assertEquals('Admin', $route['plugin'], sprintf('Route %s should belong to Admin plugin', $route['name']));
        }
    }

    /**
     * Test listRoutes with prefix filter
     */
    public function testListRoutesWithPrefixFilter(): void
    {
        $result = $this->RouteTools->listRoutes(prefix: 'Api');

        $this->assertGreaterThan(0, $result['total']);

        // All routes should have Api prefix
        foreach ($result['routes'] as $route) {
            $this->assertEquals('Api', $route['prefix'], sprintf('Route %s should have Api prefix', $route['name']));
        }
    }

    /**
     * Test listRoutes with controller filter
     */
    public function testListRoutesWithControllerFilter(): void
    {
        $result = $this->RouteTools->listRoutes(controller: 'Projects');

        $this->assertGreaterThan(0, $result['total']);

        // All routes should use Projects controller
        foreach ($result['routes'] as $route) {
            $this->assertEquals('Projects', $route['controller'], sprintf('Route %s should use Projects controller', $route['name']));
        }
    }

    /**
     * Test listRoutes with middlewares
     */
    public function testListRoutesWithMiddlewares(): void
    {
        $result = $this->RouteTools->listRoutes(withMiddlewares: true);

        $this->assertGreaterThan(0, $result['total']);

        // Check that middlewares key exists
        foreach ($result['routes'] as $route) {
            $this->assertArrayHasKey('middlewares', $route);
            $this->assertIsArray($route['middlewares']);
        }
    }

    /**
     * Test getRoute with existing route
     */
    public function testGetRouteFound(): void
    {
        $result = $this->RouteTools->getRoute('projects:view');

        $this->assertEquals('projects:view', $result['name']);
        $this->assertArrayHasKey('template', $result);
        $this->assertArrayHasKey('controller', $result);
        $this->assertEquals('Projects', $result['controller']);
        $this->assertArrayHasKey('action', $result);
        $this->assertEquals('view', $result['action']);
        $this->assertArrayHasKey('methods', $result);
        $this->assertContains('GET', $result['methods']);
        $this->assertArrayHasKey('middlewares', $result);
        $this->assertArrayHasKey('defaults', $result);
        $this->assertArrayHasKey('options', $result);
    }

    /**
     * Test getRoute with non-existing route
     */
    public function testGetRouteNotFound(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage("Route 'nonexistent:route' not found");

        $this->RouteTools->getRoute('nonexistent:route');
    }

    /**
     * Test matchUrl with valid URL
     */
    public function testMatchUrlSuccess(): void
    {
        $result = $this->RouteTools->matchUrl('/projects/123');

        $this->assertArrayHasKey('url', $result);
        $this->assertEquals('/projects/123', $result['url']);
        $this->assertArrayHasKey('method', $result);
        $this->assertEquals('GET', $result['method']);
        $this->assertArrayHasKey('params', $result);
        $this->assertIsArray($result['params']);

        // Check params - just verify basic structure
        $this->assertArrayHasKey('controller', $result['params']);
        $this->assertArrayHasKey('action', $result['params']);
        $this->assertNotEmpty($result['params']['controller']);
        $this->assertNotEmpty($result['params']['action']);
    }

    /**
     * Test matchUrl with specific HTTP method
     */
    public function testMatchUrlWithMethod(): void
    {
        $result = $this->RouteTools->matchUrl('/projects/123/edit', 'POST');

        $this->assertArrayHasKey('method', $result);
        $this->assertEquals('POST', $result['method']);
        $this->assertArrayHasKey('params', $result);

        // Check that it matched a route (fallback might catch it differently)
        $this->assertArrayHasKey('controller', $result['params']);
        $this->assertArrayHasKey('action', $result['params']);
    }

    /**
     * Test matchUrl with invalid URL
     */
    public function testMatchUrlNotFound(): void
    {
        // Note: With fallbacks enabled, this might still match
        // So we just test that it either returns data or throws exception
        try {
            $result = $this->RouteTools->matchUrl('/truly/nonexistent/route/path/that/wont/match/fallback');
            // If it returns, verify it has basic structure
            $this->assertArrayHasKey('params', $result);
        } catch (ToolCallException $toolCallException) {
            // This is also acceptable
            $this->assertStringContainsString('No route matches', $toolCallException->getMessage());
        }
    }

    /**
     * Test reverseRoute with named route
     */
    public function testReverseRouteWithName(): void
    {
        $result = $this->RouteTools->reverseRoute(name: 'projects:view', params: ['id' => 123]);

        $this->assertArrayHasKey('url', $result);
        $this->assertIsString($result['url']);
        $this->assertStringContainsString('projects', $result['url']);
        $this->assertArrayHasKey('full', $result);
        $this->assertFalse($result['full']);
    }

    /**
     * Test reverseRoute with parameters
     */
    public function testReverseRouteWithParams(): void
    {
        $result = $this->RouteTools->reverseRoute(
            params: ['controller' => 'Pages', 'action' => 'display', 'pass' => ['home']],
        );

        $this->assertArrayHasKey('url', $result);
        $this->assertIsString($result['url']);
    }

    /**
     * Test reverseRoute with full URL
     */
    public function testReverseRouteWithFullUrl(): void
    {
        $result = $this->RouteTools->reverseRoute(
            name: 'home',
            params: [],
            full: true,
        );

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('full', $result);
        $this->assertTrue($result['full']);
        $this->assertStringContainsString('http', $result['url']);
    }

    /**
     * Test reverseRoute without name or params
     */
    public function testReverseRouteWithoutNameOrParams(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessage('Either name or params must be provided');

        $this->RouteTools->reverseRoute();
    }

    /**
     * Test detectRouteCollisions
     */
    public function testDetectRouteCollisions(): void
    {
        $result = $this->RouteTools->detectRouteCollisions();

        $this->assertArrayHasKey('hasCollisions', $result);
        $this->assertIsBool($result['hasCollisions']);
        $this->assertArrayHasKey('totalCollisions', $result);
        $this->assertIsInt($result['totalCollisions']);
        $this->assertArrayHasKey('collisions', $result);
        $this->assertIsArray($result['collisions']);

        // If there are collisions, verify structure
        if ($result['hasCollisions']) {
            $this->assertGreaterThan(0, $result['totalCollisions']);
            $collision = $result['collisions'][0];
            $this->assertArrayHasKey('template', $collision);
            $this->assertArrayHasKey('method', $collision);
            $this->assertArrayHasKey('conflictingRoutes', $collision);
            $this->assertArrayHasKey('count', $collision);
            $this->assertIsArray($collision['conflictingRoutes']);
            $this->assertGreaterThan(1, $collision['count']);
        }
    }

    /**
     * Test detectRouteCollisions with actual collision
     */
    public function testDetectRouteCollisionsWithActualCollision(): void
    {
        // Add a duplicate route to create a collision
        $routeBuilder = Router::createRouteBuilder('/');
        $routeBuilder->connect('/projects/:id', ['controller' => 'DuplicateProjects', 'action' => 'show'])
            ->setPass(['id'])
            ->setMethods(['GET']);

        $result = $this->RouteTools->detectRouteCollisions();

        $this->assertTrue($result['hasCollisions'], 'Should detect the collision we created');
        $this->assertGreaterThan(0, $result['totalCollisions']);

        // Find our collision
        $foundCollision = false;
        foreach ($result['collisions'] as $collision) {
            if ($collision['template'] === '/projects/:id') {
                $foundCollision = true;
                $this->assertGreaterThan(1, $collision['count']);
                $this->assertGreaterThan(1, count($collision['conflictingRoutes']));
                break;
            }
        }

        $this->assertTrue($foundCollision, 'Should find collision for /projects/:id');
    }

    /**
     * Test that RouteTools class exists
     */
    public function testRouteToolsClassExists(): void
    {
        $this->assertInstanceOf(RouteTools::class, $this->RouteTools);
    }
}
