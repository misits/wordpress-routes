<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Route
 *
 * Represents a single route in the WordPress Routes system
 * Handles route registration, middleware, and request processing
 *
 * @since 1.0.0
 */
class Route
{
    /**
     * HTTP methods
     *
     * @var array
     */
    protected $methods;

    /**
     * Route endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Route callback
     *
     * @var callable|string
     */
    protected $callback;

    /**
     * Route namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route middleware
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Route name
     *
     * @var string
     */
    protected $name;

    /**
     * Route arguments for WordPress REST API
     *
     * @var array
     */
    protected $args = [];

    /**
     * Permission callback
     *
     * @var callable
     */
    protected $permissionCallback;

    /**
     * Create a new route instance
     *
     * @param array $methods
     * @param string $endpoint
     * @param callable|string $callback
     * @param string $namespace
     */
    public function __construct(array $methods, $endpoint, $callback, $namespace = 'wp/v2')
    {
        $this->methods = $methods;
        $this->endpoint = ltrim($endpoint, '/');
        $this->callback = $callback;
        $this->namespace = trim($namespace, '/');
    }

    /**
     * Add middleware to route
     *
     * @param array|string $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $middleware);
        return $this;
    }

    /**
     * Set route name
     *
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Set permission callback
     *
     * @param callable $callback
     * @return $this
     */
    public function permission($callback)
    {
        $this->permissionCallback = $callback;
        return $this;
    }

    /**
     * Add validation rules for a parameter
     *
     * @param string $param Parameter name
     * @param array $rules Validation rules
     * @return $this
     */
    public function validate($param, array $rules)
    {
        $this->args[$param] = $rules;
        return $this;
    }

    /**
     * Register the route with WordPress REST API
     *
     * @return bool
     */
    public function register()
    {
        return register_rest_route(
            $this->namespace,
            $this->endpoint,
            [
                'methods' => $this->methods,
                'callback' => [$this, 'handleRequest'],
                'permission_callback' => $this->permissionCallback ?: '__return_true',
                'args' => $this->args,
            ]
        );
    }

    /**
     * Handle incoming request
     *
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function handleRequest(\WP_REST_Request $request)
    {
        $routeRequest = new RouteRequest($request);

        // Process middleware
        foreach ($this->middleware as $middleware) {
            $result = $this->processMiddleware($middleware, $routeRequest);
            if ($result !== null) {
                return $result; // Middleware blocked request
            }
        }

        // Execute callback
        return $this->executeCallback($routeRequest);
    }

    /**
     * Process middleware
     *
     * @param string $middleware
     * @param RouteRequest $request
     * @return mixed|null
     */
    protected function processMiddleware($middleware, RouteRequest $request)
    {
        // Parse middleware with parameters
        if (strpos($middleware, ':') !== false) {
            [$middlewareName, $parameters] = explode(':', $middleware, 2);
            $parameters = explode(',', $parameters);
        } else {
            $middlewareName = $middleware;
            $parameters = [];
        }

        // Get middleware instance
        $middlewareInstance = MiddlewareRegistry::resolve($middlewareName);
        if (!$middlewareInstance) {
            return new \WP_Error(
                'middleware_not_found',
                "Middleware '{$middlewareName}' not found",
                ['status' => 500]
            );
        }

        // Execute middleware
        try {
            if (method_exists($middlewareInstance, 'handle')) {
                return $middlewareInstance->handle($request, ...$parameters);
            }
            
            if (is_callable($middlewareInstance)) {
                return $middlewareInstance($request, ...$parameters);
            }
            
            return new \WP_Error(
                'invalid_middleware',
                "Middleware '{$middlewareName}' is not callable",
                ['status' => 500]
            );
        } catch (\Exception $e) {
            return new \WP_Error(
                'middleware_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Execute the route callback
     *
     * @param RouteRequest $request
     * @return mixed
     */
    protected function executeCallback(RouteRequest $request)
    {
        try {
            if (is_string($this->callback) && strpos($this->callback, '@') !== false) {
                // Controller@method format
                [$controllerClass, $method] = explode('@', $this->callback);
                
                // Load controller if needed
                if (!class_exists($controllerClass)) {
                    // Try to load from controllers directory
                    $loaded = wp_routes_load_controller($controllerClass);
                    if (!$loaded) {
                        return new \WP_Error(
                            'controller_not_found',
                            "Controller '{$controllerClass}' not found",
                            ['status' => 500]
                        );
                    }
                }

                $controller = new $controllerClass();
                
                if (!method_exists($controller, $method)) {
                    return new \WP_Error(
                        'method_not_found',
                        "Method '{$method}' not found in controller '{$controllerClass}'",
                        ['status' => 500]
                    );
                }

                return $controller->$method($request);
            }

            if (is_callable($this->callback)) {
                return call_user_func($this->callback, $request);
            }

            return new \WP_Error(
                'invalid_callback',
                'Route callback is not callable',
                ['status' => 500]
            );
        } catch (\Exception $e) {
            return new \WP_Error(
                'callback_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    // Getters

    /**
     * Get route methods
     *
     * @return array
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Get route endpoint
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Get route callback
     *
     * @return callable|string
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * Get callback description for CLI display
     *
     * @return string
     */
    public function getCallbackDescription()
    {
        if (is_string($this->callback)) {
            return $this->callback;
        }
        
        if (is_callable($this->callback)) {
            if (is_array($this->callback)) {
                $class = is_object($this->callback[0]) ? get_class($this->callback[0]) : $this->callback[0];
                return $class . '@' . $this->callback[1];
            }
            return 'Closure';
        }
        
        return 'Unknown';
    }

    /**
     * Get route namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Get route middleware
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Get route name
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get route arguments
     *
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * Get full route pattern
     *
     * @return string
     */
    public function getPattern()
    {
        return '/' . $this->namespace . '/' . $this->endpoint;
    }

    /**
     * Check if route matches given method and path
     *
     * @param string $method
     * @param string $path
     * @return bool
     */
    public function matches($method, $path)
    {
        if (!in_array(strtoupper($method), $this->methods)) {
            return false;
        }

        $pattern = $this->getPattern();
        
        // Convert WordPress REST API regex patterns to standard regex
        $pattern = str_replace('(?P<', '(?<', $pattern);
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\(\?\<', '(?<', $pattern);
        
        return preg_match('/^' . $pattern . '$/', $path);
    }
}

// Helper function for controller loading (backward compatibility)
if (!function_exists('wp_routes_load_controller')) {
    function wp_routes_load_controller($controllerName)
    {
        // Try existing function first
        if (function_exists('wproutes_load_controller')) {
            return wproutes_load_controller($controllerName);
        }
        
        // Fallback loading logic
        $paths = defined('WPROUTES_CONTROLLER_PATHS') ? WPROUTES_CONTROLLER_PATHS : [];
        
        foreach ($paths as $path) {
            $file = $path . '/' . $controllerName . '.php';
            if (file_exists($file)) {
                require_once $file;
                return class_exists($controllerName);
            }
        }
        
        return false;
    }
}