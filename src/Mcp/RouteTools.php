<?php
declare(strict_types=1);

namespace Synapse\Mcp;

use Cake\Http\ServerRequest;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Exception;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;

/**
 * Route Tools
 *
 * MCP tools for inspecting and working with CakePHP routes.
 */
class RouteTools
{
    /**
     * List all registered routes in the application.
     *
     * Returns a list of all routes with filtering and sorting options.
     * Useful for understanding the application's routing structure.
     *
     * @param bool $sort Sort routes alphabetically by name
     * @param string|null $method Filter by HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string|null $plugin Filter by plugin name
     * @param string|null $prefix Filter by route prefix
     * @param string|null $controller Filter by controller name
     * @param bool $withMiddlewares Include middleware information
     * @return array<string, mixed> List of routes with metadata
     */
    #[McpTool(
        name: 'list_routes',
        description: 'List all registered routes with optional filtering and sorting',
    )]
    public function listRoutes(
        bool $sort = false,
        ?string $method = null,
        ?string $plugin = null,
        ?string $prefix = null,
        ?string $controller = null,
        bool $withMiddlewares = false,
    ): array {
        $availableRoutes = Router::routes();
        $routes = [];

        foreach ($availableRoutes as $route) {
            $methods = isset($route->defaults['_method']) ? (array)$route->defaults['_method'] : [];
            $routePlugin = $route->defaults['plugin'] ?? null;
            $routePrefix = $route->defaults['prefix'] ?? null;
            $routeController = $route->defaults['controller'] ?? null;

            // Apply filters
            if ($method !== null && $methods !== []) {
                $upperMethods = array_map('strtoupper', $methods);
                if (!in_array(strtoupper($method), $upperMethods)) {
                    continue;
                }
            }

            if ($plugin !== null && $routePlugin !== $plugin) {
                continue;
            }

            if ($prefix !== null && $routePrefix !== $prefix) {
                continue;
            }

            if ($controller !== null && $routeController !== $controller) {
                continue;
            }

            $routeData = [
                'name' => $route->options['_name'] ?? $route->getName(),
                'template' => $route->template,
                'plugin' => $routePlugin,
                'prefix' => $routePrefix,
                'controller' => $routeController,
                'action' => $route->defaults['action'] ?? null,
                'methods' => $methods,
            ];

            if ($withMiddlewares) {
                $routeData['middlewares'] = $route->getMiddleware();
            }

            $routes[] = $routeData;
        }

        if ($sort) {
            usort($routes, function (array $a, array $b): int {
                return strcasecmp((string)$a['name'], (string)$b['name']);
            });
        }

        return [
            'total' => count($routes),
            'routes' => $routes,
        ];
    }

    /**
     * Get detailed information about a specific named route.
     *
     * Returns comprehensive information about a route including its
     * template, controller/action, methods, middlewares, and defaults.
     *
     * @param string $name The route name to look up
     * @return array<string, mixed> Route details or error
     */
    #[McpTool(
        name: 'get_route',
        description: 'Get detailed information about a specific named route',
    )]
    public function getRoute(string $name): array
    {
        $availableRoutes = Router::routes();

        foreach ($availableRoutes as $route) {
            $routeName = $route->options['_name'] ?? $route->getName();

            if ($routeName === $name) {
                return [
                    'name' => $routeName,
                    'template' => $route->template,
                    'plugin' => $route->defaults['plugin'] ?? null,
                    'prefix' => $route->defaults['prefix'] ?? null,
                    'controller' => $route->defaults['controller'] ?? null,
                    'action' => $route->defaults['action'] ?? null,
                    'methods' => isset($route->defaults['_method']) ? (array)$route->defaults['_method'] : [],
                    'middlewares' => $route->getMiddleware(),
                    'defaults' => $route->defaults,
                    'options' => $route->options,
                ];
            }
        }

        throw new ToolCallException(sprintf("Route '%s' not found", $name));
    }

    /**
     * Find which route matches a given URL path.
     *
     * Parses a URL path to determine which route handles it and
     * extracts the route parameters.
     *
     * @param string $url The URL path to match (e.g., '/projects/123')
     * @param string $method HTTP method (default: 'GET')
     * @return array<string, mixed> Matched route information and parameters
     */
    #[McpTool(
        name: 'match_url',
        description: 'Find which route matches a given URL path',
    )]
    public function matchUrl(string $url, string $method = 'GET'): array
    {
        try {
            // Create a mock ServerRequest for route parsing
            $request = new ServerRequest([
                'url' => $url,
                'environment' => [
                    'REQUEST_METHOD' => strtoupper($method),
                ],
            ]);

            $params = Router::parseRequest($request);

            return [
                'url' => $url,
                'method' => strtoupper($method),
                'params' => $params,
            ];
        } catch (MissingRouteException $e) {
            $message = sprintf("No route matches URL '%s' with method '%s': %s", $url, $method, $e->getMessage());
            throw new ToolCallException($message);
        } catch (Exception $e) {
            $message = sprintf("Error parsing URL '%s': %s", $url, $e->getMessage());
            throw new ToolCallException($message);
        }
    }

    /**
     * Generate URL from route parameters (reverse routing).
     *
     * Creates a URL from either a named route or route parameters.
     * Supports generating full URLs with domain.
     *
     * @param string|null $name Named route (e.g., 'projects:view')
     * @param array<string, mixed> $params Route parameters
     * @param bool $full Generate full URL with domain
     * @return array<string, mixed> Generated URL or error
     */
    #[McpTool(
        name: 'reverse_route',
        description: 'Generate URL from route name or parameters (reverse routing)',
    )]
    public function reverseRoute(
        ?string $name = null,
        #[Schema(
            type: 'object',
            description: 'Route parameters like controller, action, plugin, prefix, and pass parameters. ' .
                'Examples: {"controller": "Articles", "action": "view", "id": "123"} or ' .
                '{"plugin": "MyPlugin", "controller": "Users", "action": "index"}',
            additionalProperties: true,
        )]
        array $params = [],
        bool $full = false,
    ): array {
        try {
            $url = null;

            if ($name !== null) {
                // Named route - merge params properly
                $urlArray = ['_name' => $name];
                foreach ($params as $key => $value) {
                    $urlArray[$key] = $value;
                }

                $url = Router::url($urlArray, $full);
            } elseif ($params !== []) {
                // Parameter-based routing
                $url = Router::url($params, $full);
            } else {
                throw new ToolCallException('Either name or params must be provided');
            }

            return [
                'url' => $url,
                'full' => $full,
            ];
        } catch (Exception $exception) {
            throw new ToolCallException('Error generating URL: ' . $exception->getMessage());
        }
    }

    /**
     * Detect potential route collisions and conflicts.
     *
     * Analyzes all routes to find potential conflicts where multiple
     * routes could match the same URL pattern and HTTP method.
     *
     * @return array<string, mixed> List of route collisions
     */
    #[McpTool(
        name: 'detect_route_collisions',
        description: 'Detect potential route collisions and conflicts',
    )]
    public function detectRouteCollisions(): array
    {
        $availableRoutes = Router::routes();
        $duplicateRoutesCounter = [];
        $collisions = [];

        // Count duplicates
        foreach ($availableRoutes as $route) {
            $methods = isset($route->defaults['_method']) ? (array)$route->defaults['_method'] : [''];

            foreach ($methods as $method) {
                $duplicateRoutesCounter[$route->template][$method] ??= [];
                $duplicateRoutesCounter[$route->template][$method][] = [
                    'name' => $route->options['_name'] ?? $route->getName(),
                    'plugin' => $route->defaults['plugin'] ?? null,
                    'prefix' => $route->defaults['prefix'] ?? null,
                    'controller' => $route->defaults['controller'] ?? null,
                    'action' => $route->defaults['action'] ?? null,
                ];
            }
        }

        // Find collisions
        foreach ($duplicateRoutesCounter as $template => $methodCounts) {
            foreach ($methodCounts as $method => $routes) {
                if (
                    count($routes) > 1 ||
                    ($method === '' && count($methodCounts) > 1) ||
                    ($method !== '' && isset($methodCounts['']))
                ) {
                    $collisions[] = [
                        'template' => $template,
                        'method' => $method ?: 'ANY',
                        'conflictingRoutes' => $routes,
                        'count' => count($routes),
                    ];
                }
            }
        }

        return [
            'hasCollisions' => $collisions !== [],
            'totalCollisions' => count($collisions),
            'collisions' => $collisions,
        ];
    }
}
