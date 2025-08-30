<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Route Manager
 *
 * Main class for managing WordPress REST API routes with middleware support
 * Provides Laravel-like routing experience for WordPress
 *
 * @since 1.0.0
 */
class RouteManager
{
    /**
     * Registered routes
     *
     * @var array
     */
    protected static $routes = [];

    /**
     * Default namespace
     *
     * @var string
     */
    protected static $defaultNamespace = 'wp/v2';

    /**
     * Custom REST API prefix
     *
     * @var string|null
     */
    protected static $restUrlPrefix = null;

    /**
     * Global middleware
     *
     * @var array
     */
    protected static $globalMiddleware = [];

    /**
     * Route groups stack
     *
     * @var array
     */
    protected static $groupStack = [];

    /**
     * Initialize the Route manager
     */
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
    }

    /**
     * Boot the routing system
     */
    public static function boot()
    {
        // Initialize middleware registry
        MiddlewareRegistry::init();
        
        // Set up REST URL prefix filter if custom prefix is set
        if (self::$restUrlPrefix !== null) {
            add_filter('rest_url_prefix', [__CLASS__, 'getRestUrlPrefix']);
        }
        
        self::init();
    }

    /**
     * Set the default namespace
     *
     * @param string $namespace
     */
    public static function setNamespace($namespace)
    {
        self::$defaultNamespace = $namespace;
    }

    /**
     * Get the default namespace
     *
     * @return string
     */
    public static function getNamespace()
    {
        return self::$defaultNamespace;
    }

    /**
     * Set the REST API prefix
     *
     * @param string $prefix
     */
    public static function setApiPrefix($prefix)
    {
        self::$restUrlPrefix = $prefix;
        
        // Add filter immediately if WordPress is loaded
        if (did_action('init')) {
            add_filter('rest_url_prefix', [__CLASS__, 'getRestUrlPrefix']);
        }
    }

    /**
     * Get the REST API prefix
     *
     * @return string
     */
    public static function getRestUrlPrefix()
    {
        return self::$restUrlPrefix ?: 'wp-json';
    }

    /**
     * Add global middleware
     *
     * @param array|string $middleware
     */
    public static function middleware($middleware)
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        self::$globalMiddleware = array_merge(self::$globalMiddleware, $middleware);
    }

    /**
     * Register a GET route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function get($endpoint, $callback)
    {
        return self::addRoute(['GET'], $endpoint, $callback);
    }

    /**
     * Register a POST route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function post($endpoint, $callback)
    {
        return self::addRoute(['POST'], $endpoint, $callback);
    }

    /**
     * Register a PUT route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function put($endpoint, $callback)
    {
        return self::addRoute(['PUT'], $endpoint, $callback);
    }

    /**
     * Register a PATCH route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function patch($endpoint, $callback)
    {
        return self::addRoute(['PATCH'], $endpoint, $callback);
    }

    /**
     * Register a DELETE route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function delete($endpoint, $callback)
    {
        return self::addRoute(['DELETE'], $endpoint, $callback);
    }

    /**
     * Register a route for any method
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function any($endpoint, $callback)
    {
        return self::addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $endpoint, $callback);
    }

    /**
     * Register a route for specific methods
     *
     * @param array $methods
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    public static function match(array $methods, $endpoint, $callback)
    {
        return self::addRoute($methods, $endpoint, $callback);
    }

    /**
     * Register resource routes
     *
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return array
     */
    public static function resource($name, $controller, array $options = [])
    {
        $only = $options['only'] ?? ['index', 'show', 'store', 'update', 'destroy'];
        $except = $options['except'] ?? [];
        $actions = array_diff($only, $except);

        $routes = [];
        $resourceRoutes = [
            'index' => ['GET', $name, 'index'],
            'show' => ['GET', $name . '/(?P<id>[\d]+)', 'show'],
            'store' => ['POST', $name, 'store'],
            'update' => ['PUT', $name . '/(?P<id>[\d]+)', 'update'],
            'destroy' => ['DELETE', $name . '/(?P<id>[\d]+)', 'destroy'],
        ];

        foreach ($actions as $action) {
            if (isset($resourceRoutes[$action])) {
                [$method, $endpoint, $method_name] = $resourceRoutes[$action];
                $callback = $controller . '@' . $method_name;
                $routes[$action] = self::addRoute([$method], $endpoint, $callback);
            }
        }

        return $routes;
    }

    /**
     * Create a route group
     *
     * @param array $attributes
     * @param callable $callback
     */
    public static function group(array $attributes, callable $callback)
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    /**
     * Add a route
     *
     * @param array $methods
     * @param string $endpoint
     * @param callable|string $callback
     * @return Route
     */
    protected static function addRoute(array $methods, $endpoint, $callback)
    {
        // Apply group attributes
        $groupAttributes = self::mergeGroupAttributes();
        $namespace = $groupAttributes['namespace'] ?? self::$defaultNamespace;
        $middleware = array_merge(
            self::$globalMiddleware,
            $groupAttributes['middleware'] ?? [],
        );

        // Apply prefix
        if (!empty($groupAttributes['prefix'])) {
            $endpoint = trim($groupAttributes['prefix'], '/') . '/' . ltrim($endpoint, '/');
        }

        // Create route instance (default to API type for RouteManager routes)
        $route = new Route($methods, $endpoint, $callback, Route::TYPE_API);
        
        // Set the namespace explicitly
        $route->setNamespace($namespace);

        // Apply middleware
        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        // Store route
        $routeKey = implode('|', $methods) . ':' . $namespace . '/' . $endpoint;
        self::$routes[$routeKey] = $route;

        return $route;
    }

    /**
     * Get current group attributes (public access for Route class)
     *
     * @return array
     */
    public static function getCurrentGroupAttributes()
    {
        return self::mergeGroupAttributes();
    }

    /**
     * Merge group attributes from stack
     *
     * @return array
     */
    protected static function mergeGroupAttributes()
    {
        $attributes = [];
        
        foreach (self::$groupStack as $group) {
            // Merge namespace
            if (isset($group['namespace'])) {
                $attributes['namespace'] = isset($attributes['namespace'])
                    ? $attributes['namespace'] . '/' . trim($group['namespace'], '/')
                    : $group['namespace'];
            }

            // Merge prefix
            if (isset($group['prefix'])) {
                $attributes['prefix'] = isset($attributes['prefix'])
                    ? trim($attributes['prefix'], '/') . '/' . trim($group['prefix'], '/')
                    : $group['prefix'];
            }

            // Merge middleware
            if (isset($group['middleware'])) {
                $middleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $attributes['middleware'] = array_merge($attributes['middleware'] ?? [], $middleware);
            }
        }

        return $attributes;
    }

    /**
     * Register all routes with WordPress
     */
    public static function registerRoutes()
    {
        foreach (self::$routes as $route) {
            $route->register();
        }
    }

    /**
     * Get all registered routes
     *
     * @return array
     */
    public static function getRoutes()
    {
        return self::$routes;
    }

    /**
     * Get registered routes for CLI display
     *
     * @return array
     */
    public static function getRegisteredRoutes()
    {
        $routes = [];
        foreach (self::$routes as $key => $route) {
            // Only include API routes in this list
            if ($route->getType() === Route::TYPE_API) {
                $routes[] = [
                    'methods' => implode(', ', $route->getMethods()),
                    'endpoint' => ($route->getNamespace() ?: self::$defaultNamespace) . '/' . $route->getEndpoint(),
                    'callback' => $route->getCallbackDescription() ?? 'Custom Handler',
                    'middleware' => implode(', ', $route->getMiddleware()),
                ];
            }
        }
        return $routes;
    }

    /**
     * Generate URL for named route
     *
     * @param string $name
     * @param array $params
     * @return string|null
     */
    public static function url($name, array $params = [])
    {
        foreach (self::$routes as $route) {
            if ($route->getName() === $name) {
                // Generate URL based on route type
                $endpoint = $route->getEndpoint();
                
                // Replace parameters in endpoint
                foreach ($params as $key => $value) {
                    $endpoint = str_replace('{' . $key . '}', $value, $endpoint);
                    $endpoint = preg_replace('/\(\?\P<' . $key . '>[^)]+\)/', $value, $endpoint);
                }
                
                // Generate appropriate URL based on route type
                switch ($route->getType()) {
                    case 'web':
                    case 'admin':
                        // Web routes use home_url() for regular WordPress URLs
                        return home_url('/' . ltrim($endpoint, '/'));
                        
                    case 'ajax':
                        // AJAX routes use admin_url() with admin-ajax.php
                        return admin_url('admin-ajax.php?action=' . $endpoint);
                        
                    case 'api':
                    case 'webhook':
                    default:
                        // API routes use rest_url() for REST API endpoints
                        return rest_url($route->getNamespace() . '/' . $endpoint);
                }
            }
        }
        
        return null;
    }

    /**
     * Clear all routes (useful for testing)
     */
    public static function clearRoutes()
    {
        self::$routes = [];
        self::$namedRoutes = [];
        self::$groupStack = [];
        self::$globalMiddleware = [];
        self::$restUrlPrefix = null;
    }

    /**
     * Named routes collection
     *
     * @var array
     */
    protected static $namedRoutes = [];

    /**
     * Add a named route
     *
     * @param string $name
     * @param Route $route
     */
    public static function addNamedRoute($name, Route $route)
    {
        self::$namedRoutes[$name] = $route;
    }

    /**
     * Get named route
     *
     * @param string $name
     * @return Route|null
     */
    public static function getNamedRoute($name)
    {
        return self::$namedRoutes[$name] ?? null;
    }

    /**
     * Add a route instance to the collection
     *
     * @param Route $route
     */
    public static function addRouteInstance(Route $route)
    {
        $routeKey = implode('|', $route->getMethods()) . ':' . 
                   ($route->getNamespace() ?: self::$defaultNamespace) . '/' . 
                   $route->getEndpoint();
        self::$routes[$routeKey] = $route;
    }
}