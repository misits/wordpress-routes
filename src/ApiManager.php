<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * API Manager
 *
 * Main class for managing WordPress REST API routes with middleware support
 * Provides Laravel-like routing experience for WordPress
 *
 * @since 1.0.0
 */
class ApiManager
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
     * Initialize the API manager
     */
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'registerRoutes']);
    }

    /**
     * Set default namespace
     *
     * @param string $namespace
     */
    public static function setNamespace($namespace)
    {
        static::$defaultNamespace = $namespace;
    }

    /**
     * Get default namespace
     *
     * @return string
     */
    public static function getNamespace()
    {
        return static::$defaultNamespace;
    }

    /**
     * Create GET route
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function get($endpoint, $handler)
    {
        return static::addRoute('GET', $endpoint, $handler);
    }

    /**
     * Create POST route
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function post($endpoint, $handler)
    {
        return static::addRoute('POST', $endpoint, $handler);
    }

    /**
     * Create PUT route
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function put($endpoint, $handler)
    {
        return static::addRoute('PUT', $endpoint, $handler);
    }

    /**
     * Create DELETE route
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function delete($endpoint, $handler)
    {
        return static::addRoute('DELETE', $endpoint, $handler);
    }

    /**
     * Create PATCH route
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function patch($endpoint, $handler)
    {
        return static::addRoute('PATCH', $endpoint, $handler);
    }

    /**
     * Create route for multiple methods
     *
     * @param array $methods
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function match(array $methods, $endpoint, $handler)
    {
        return static::addRoute($methods, $endpoint, $handler);
    }

    /**
     * Create route for any method
     *
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    public static function any($endpoint, $handler)
    {
        return static::addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $endpoint, $handler);
    }

    /**
     * Add route to collection
     *
     * @param string|array $methods
     * @param string $endpoint
     * @param callable $handler
     * @return ApiRoute
     */
    protected static function addRoute($methods, $endpoint, $handler)
    {
        $namespace = static::getCurrentNamespace();
        $route = new ApiRoute($namespace, $endpoint, (array) $methods);
        $route->handler($handler);

        // Apply group middleware
        if (!empty(static::$groupStack)) {
            $groupMiddleware = [];
            foreach (static::$groupStack as $group) {
                if (isset($group['middleware'])) {
                    $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
                }
            }
            if (!empty($groupMiddleware)) {
                $route->middleware($groupMiddleware);
            }
        }

        // Apply global middleware
        if (!empty(static::$globalMiddleware)) {
            $route->middleware(static::$globalMiddleware);
        }

        static::$routes[] = $route;
        return $route;
    }

    /**
     * Register route group
     *
     * @param array $attributes
     * @param callable $callback
     */
    public static function group(array $attributes, callable $callback)
    {
        static::$groupStack[] = $attributes;
        
        call_user_func($callback);
        
        array_pop(static::$groupStack);
    }

    /**
     * Add global middleware
     *
     * @param string|array $middleware
     */
    public static function middleware($middleware)
    {
        static::$globalMiddleware = array_merge(static::$globalMiddleware, (array) $middleware);
    }

    /**
     * Get current namespace from group stack
     *
     * @return string
     */
    protected static function getCurrentNamespace()
    {
        foreach (array_reverse(static::$groupStack) as $group) {
            if (isset($group['namespace'])) {
                return $group['namespace'];
            }
        }
        
        return static::$defaultNamespace;
    }

    /**
     * Register all routes with WordPress
     */
    public static function registerRoutes()
    {
        foreach (static::$routes as $route) {
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
        return static::$routes;
    }

    /**
     * Clear all routes (useful for testing)
     */
    public static function clearRoutes()
    {
        static::$routes = [];
        static::$globalMiddleware = [];
        static::$groupStack = [];
    }

    /**
     * Create a resource route set (CRUD operations)
     *
     * @param string $name Resource name
     * @param string $controller Controller class
     * @param array $options Options (only, except, etc.)
     * @return array Array of created routes
     */
    public static function resource($name, $controller, array $options = [])
    {
        $routes = [];
        $actions = [
            'index' => ['GET', $name],
            'show' => ['GET', $name . '/(?P<id>[\d]+)'],
            'store' => ['POST', $name],
            'update' => ['PUT', $name . '/(?P<id>[\d]+)'],
            'destroy' => ['DELETE', $name . '/(?P<id>[\d]+)'],
        ];

        // Filter actions based on options
        if (isset($options['only'])) {
            $actions = array_intersect_key($actions, array_flip((array) $options['only']));
        }
        
        if (isset($options['except'])) {
            $actions = array_diff_key($actions, array_flip((array) $options['except']));
        }

        foreach ($actions as $action => $definition) {
            list($method, $endpoint) = $definition;
            $handler = $controller . '@' . $action;
            $routes[$action] = static::addRoute($method, $endpoint, $handler);
        }

        return $routes;
    }

    /**
     * Generate URL for named route
     *
     * @param string $name Route name
     * @param array $params Parameters
     * @return string|null
     */
    public static function url($name, array $params = [])
    {
        foreach (static::$routes as $route) {
            if ($route->getName() === $name) {
                return $route->url($params);
            }
        }
        
        return null;
    }

    /**
     * Boot the API manager
     */
    public static function boot()
    {
        static::init();
        
        // Load default middleware if not already loaded
        if (!class_exists('WordPressRoutes\Routing\Middleware\AuthMiddleware')) {
            // Middleware will be auto-loaded by autoloader
        }
    }
}