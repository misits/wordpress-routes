<?php

namespace WordPressRoutes\Routing\Traits;

use WordPressRoutes\Routing\RouteRequest;
use WordPressRoutes\Routing\ControllerAutoloader;
use WordPressRoutes\Routing\MiddlewareRegistry;
use WordPressRoutes\Routing\RouteManager;

defined("ABSPATH") or exit();

/**
 * Trait for handling API routes
 *
 * @since 1.0.0
 */
trait HandlesApiRoutes
{
    /**
     * Register API route
     */
    protected function registerApiRoute()
    {
        add_action('rest_api_init', function() {
            // Get namespace - prefer instance namespace, fall back to RouteManager
            $namespace = $this->namespace ?: RouteManager::getNamespace();
            
            register_rest_route(
                $namespace,
                '/' . $this->endpoint,
                [
                    'methods' => $this->methods,
                    'callback' => [$this, 'handleApiRequest'],
                    'permission_callback' => '__return_true'
                ]
            );
        });
    }

    /**
     * Handle API request
     *
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public function handleApiRequest(\WP_REST_Request $request)
    {
        $routeRequest = new RouteRequest($request);

        // Process middleware
        foreach ($this->middleware as $middleware) {
            $result = $this->processMiddleware($middleware, $routeRequest);
            if ($result !== null) {
                return $result;
            }
        }

        // Execute callback
        return $this->executeCallback($routeRequest);
    }

    /**
     * Process middleware for API routes
     *
     * @param string|callable $middleware
     * @param RouteRequest $request
     * @return mixed|null
     */
    protected function processMiddleware($middleware, RouteRequest $request)
    {
        // Handle callable middleware directly
        if (is_callable($middleware)) {
            try {
                return $middleware($request);
            } catch (\Exception $e) {
                return new \WP_Error(
                    'middleware_error',
                    $e->getMessage(),
                    ['status' => 500]
                );
            }
        }

        // Handle string middleware with parameters
        if (is_string($middleware) && strpos($middleware, ':') !== false) {
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
     * Execute callback for API routes
     *
     * @param RouteRequest $request
     * @return mixed
     */
    protected function executeCallback($request)
    {
        if (is_string($this->callback)) {
            if (strpos($this->callback, '@') !== false) {
                [$class, $method] = explode('@', $this->callback, 2);
                $controller = ControllerAutoloader::resolve($class);
                
                if (!$controller) {
                    return new \WP_Error(
                        'controller_not_found',
                        "Controller '{$class}' not found",
                        ['status' => 500]
                    );
                }
                
                return $controller->$method($request);
            }
        }

        return call_user_func($this->callback, $request);
    }

    /**
     * Set namespace for API route
     *
     * @param string $namespace
     * @return self
     */
    public function namespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
}