<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * API Route Class
 *
 * Creates powerful REST API endpoints within WordPress
 * Supports middleware, authentication, rate limiting, etc.
 * Works with WordPress REST API infrastructure
 *
 * @since 1.0.0
 */
class ApiRoute
{
    /**
     * API namespace
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * HTTP methods
     *
     * @var array
     */
    protected $methods = ['GET'];

    /**
     * Route callback
     *
     * @var callable
     */
    protected $callback;

    /**
     * Route middleware stack
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Permission callback
     *
     * @var callable|null
     */
    protected $permissionCallback;

    /**
     * Route arguments/parameters
     *
     * @var array
     */
    protected $args = [];

    /**
     * Create new API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @param array $methods
     */
    public function __construct($namespace, $endpoint, $methods = ['GET'])
    {
        $this->namespace = $namespace;
        $this->endpoint = ltrim($endpoint, '/');
        $this->methods = (array) $methods;
    }

    /**
     * Create GET API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @return static
     */
    public static function get($namespace, $endpoint)
    {
        return new static($namespace, $endpoint, ['GET']);
    }

    /**
     * Create POST API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @return static
     */
    public static function post($namespace, $endpoint)
    {
        return new static($namespace, $endpoint, ['POST']);
    }

    /**
     * Create PUT API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @return static
     */
    public static function put($namespace, $endpoint)
    {
        return new static($namespace, $endpoint, ['PUT']);
    }

    /**
     * Create DELETE API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @return static
     */
    public static function delete($namespace, $endpoint)
    {
        return new static($namespace, $endpoint, ['DELETE']);
    }

    /**
     * Create PATCH API route
     *
     * @param string $namespace
     * @param string $endpoint
     * @return static
     */
    public static function patch($namespace, $endpoint)
    {
        return new static($namespace, $endpoint, ['PATCH']);
    }

    /**
     * Create route for multiple methods
     *
     * @param string $namespace
     * @param string $endpoint
     * @param array $methods
     * @return static
     */
    public static function match($namespace, $endpoint, array $methods)
    {
        return new static($namespace, $endpoint, $methods);
    }

    /**
     * Set route callback/handler
     *
     * @param callable $callback
     * @return $this
     */
    public function handler($callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Add middleware to the route
     *
     * @param string|callable|array $middleware
     * @return $this
     */
    public function middleware($middleware)
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
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
     * Require authentication
     *
     * @return $this
     */
    public function auth()
    {
        return $this->permission(function() {
            return is_user_logged_in();
        });
    }

    /**
     * Require specific capability
     *
     * @param string $capability
     * @return $this
     */
    public function can($capability)
    {
        return $this->permission(function() use ($capability) {
            return current_user_can($capability);
        });
    }

    /**
     * Set route arguments/validation
     *
     * @param array $args
     * @return $this
     */
    public function args(array $args)
    {
        $this->args = array_merge($this->args, $args);
        return $this;
    }

    /**
     * Add parameter validation
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
     * Handle the API request with middleware support
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error|mixed
     */
    public function handleRequest(\WP_REST_Request $request)
    {
        try {
            // Create our request wrapper
            $apiRequest = new ApiRequest($request);

            // Run middleware stack
            $response = $this->runMiddleware($apiRequest);
            if ($response !== null) {
                return $this->formatResponse($response);
            }

            // Execute main handler
            $result = $this->executeHandler($apiRequest);
            return $this->formatResponse($result);

        } catch (\Exception $e) {
            return new \WP_Error(
                'api_error',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 500]
            );
        }
    }

    /**
     * Run middleware stack
     *
     * @param ApiRequest $request
     * @return mixed|null
     */
    protected function runMiddleware(ApiRequest $request)
    {
        foreach ($this->middleware as $middleware) {
            $result = $this->executeMiddleware($middleware, $request);
            if ($result !== null) {
                return $result;
            }
        }
        return null;
    }

    /**
     * Execute single middleware
     *
     * @param string|callable $middleware
     * @param ApiRequest $request
     * @return mixed|null
     */
    protected function executeMiddleware($middleware, ApiRequest $request)
    {
        if (is_callable($middleware)) {
            return call_user_func($middleware, $request);
        }

        if (is_string($middleware)) {
            // Handle named middleware from registry
            $middlewareInstance = MiddlewareRegistry::get($middleware);
            if ($middlewareInstance) {
                return $middlewareInstance->handle($request);
            }

            // Handle class@method format
            if (strpos($middleware, '@') !== false) {
                list($class, $method) = explode('@', $middleware, 2);
                if (class_exists($class)) {
                    $instance = new $class();
                    if (method_exists($instance, $method)) {
                        return call_user_func([$instance, $method], $request);
                    }
                }
            }

            // Handle class with handle method
            if (class_exists($middleware)) {
                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    return $instance->handle($request);
                }
            }
        }

        return null;
    }

    /**
     * Execute the main handler
     *
     * @param ApiRequest $request
     * @return mixed
     */
    protected function executeHandler(ApiRequest $request)
    {
        if (!$this->callback) {
            throw new \RuntimeException('No handler defined for route');
        }

        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $request);
        }

        if (is_string($this->callback) && strpos($this->callback, '@') !== false) {
            list($class, $method) = explode('@', $this->callback, 2);
            if (class_exists($class)) {
                $instance = new $class();
                
                // If controller extends BaseController, inject the request
                if ($instance instanceof BaseController) {
                    $instance->setRequest($request);
                }
                
                if (method_exists($instance, $method)) {
                    return call_user_func([$instance, $method], $request);
                }
            }
        }

        throw new \RuntimeException('Invalid route handler');
    }

    /**
     * Format response for WordPress REST API
     *
     * @param mixed $response
     * @return \WP_REST_Response|mixed
     */
    protected function formatResponse($response)
    {
        if ($response instanceof \WP_REST_Response || $response instanceof \WP_Error) {
            return $response;
        }

        if (is_array($response) || is_object($response)) {
            return new \WP_REST_Response($response, 200);
        }

        return $response;
    }

    /**
     * Get the full API URL for this route
     *
     * @param array $params URL parameters
     * @return string
     */
    public function url(array $params = [])
    {
        $endpoint = $this->endpoint;
        
        // Replace route parameters
        foreach ($params as $key => $value) {
            $endpoint = str_replace('(?P<' . $key . '>[\d]+)', $value, $endpoint);
            $endpoint = str_replace('(?P<' . $key . '>[^/]+)', $value, $endpoint);
        }
        
        return rest_url($this->namespace . '/' . $endpoint);
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
     * Get route endpoint
     *
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

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
     * Get route middleware
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }
}