<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Middleware Registry
 *
 * Manages middleware registration and retrieval for API routes
 * Allows registering middleware by name for easy reuse
 *
 * @since 1.0.0
 */
class MiddlewareRegistry
{
    /**
     * Registered middleware
     *
     * @var array
     */
    protected static $middleware = [];

    /**
     * Built-in middleware
     *
     * @var array
     */
    protected static $builtIn = [];

    /**
     * Initialize built-in middleware
     */
    public static function init()
    {
        static::$builtIn = [
            'auth' => Middleware\AuthMiddleware::class,
            'capability' => Middleware\CapabilityMiddleware::class,
            'rate_limit' => Middleware\RateLimitMiddleware::class,
            'cors' => Middleware\CorsMiddleware::class,
            'validate' => Middleware\ValidationMiddleware::class,
            'json_only' => Middleware\JsonOnlyMiddleware::class,
            'nonce' => Middleware\NonceMiddleware::class,
        ];
    }

    /**
     * Register middleware
     *
     * @param string $name
     * @param string|callable $middleware
     */
    public static function register($name, $middleware)
    {
        static::$middleware[$name] = $middleware;
    }

    /**
     * Register multiple middleware
     *
     * @param array $middleware
     */
    public static function registerMany(array $middleware)
    {
        foreach ($middleware as $name => $class) {
            static::register($name, $class);
        }
    }

    /**
     * Get middleware instance
     *
     * @param string $name
     * @return object|callable|null
     */
    public static function get($name)
    {
        // Check user registered middleware first
        if (isset(static::$middleware[$name])) {
            return static::resolve(static::$middleware[$name]);
        }

        // Check built-in middleware
        if (isset(static::$builtIn[$name])) {
            return static::resolve(static::$builtIn[$name]);
        }

        return null;
    }

    /**
     * Get all registered middleware names
     *
     * @return array
     */
    public static function all()
    {
        return array_merge(array_keys(static::$builtIn), array_keys(static::$middleware));
    }

    /**
     * Check if middleware exists
     *
     * @param string $name
     * @return bool
     */
    public static function has($name)
    {
        return isset(static::$middleware[$name]) || isset(static::$builtIn[$name]);
    }

    /**
     * Remove middleware
     *
     * @param string $name
     */
    public static function remove($name)
    {
        unset(static::$middleware[$name]);
    }

    /**
     * Clear all user-registered middleware
     */
    public static function clear()
    {
        static::$middleware = [];
    }

    /**
     * Resolve middleware to instance
     *
     * @param string|callable $middleware
     * @return object|callable
     */
    protected static function resolve($middleware)
    {
        if (is_callable($middleware)) {
            return $middleware;
        }

        if (is_string($middleware) && class_exists($middleware)) {
            return new $middleware();
        }

        return $middleware;
    }

    /**
     * Create middleware group
     *
     * @param string $name
     * @param array $middleware
     */
    public static function group($name, array $middleware)
    {
        static::register($name, function(ApiRequest $request) use ($middleware) {
            foreach ($middleware as $middlewareName) {
                $instance = static::get($middlewareName);
                if ($instance && method_exists($instance, 'handle')) {
                    $result = $instance->handle($request);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
            return null;
        });
    }

    /**
     * Get built-in middleware list
     *
     * @return array
     */
    public static function getBuiltIn()
    {
        return static::$builtIn;
    }
}

// Initialize built-in middleware
MiddlewareRegistry::init();