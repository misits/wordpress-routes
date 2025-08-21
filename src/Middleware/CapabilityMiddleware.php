<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * Capability Middleware
 *
 * Checks if the current user has the required WordPress capability
 * Can be used for fine-grained permission control in routes
 *
 * @since 1.0.0
 */
class CapabilityMiddleware implements MiddlewareInterface
{
    /**
     * Handle the middleware request
     *
     * @param RouteRequest $request
     * @param string $capability The required capability
     * @return \WP_Error|null
     */
    public function handle(RouteRequest $request, $capability = 'read')
    {
        // Check if user is authenticated first
        if (!$request->isAuthenticated()) {
            return new \WP_Error(
                'unauthenticated',
                'Authentication required',
                ['status' => 401]
            );
        }

        // Check if user has the required capability
        if (!current_user_can($capability)) {
            return new \WP_Error(
                'insufficient_capability',
                sprintf('You need the "%s" capability to access this resource', $capability),
                ['status' => 403]
            );
        }

        // User has the required capability, allow request to continue
        return null;
    }

    /**
     * Check if user has capability for a specific post/object
     *
     * @param RouteRequest $request
     * @param string $capability
     * @param int|null $objectId
     * @return \WP_Error|null
     */
    public function handleWithObject(RouteRequest $request, $capability, $objectId = null)
    {
        // Check if user is authenticated first
        if (!$request->isAuthenticated()) {
            return new \WP_Error(
                'unauthenticated',
                'Authentication required',
                ['status' => 401]
            );
        }

        // If no object ID provided, try to get from request
        if ($objectId === null) {
            $objectId = $request->param('id') ?: $request->input('id');
        }

        // Check capability with object context
        $hasCapability = $objectId 
            ? current_user_can($capability, $objectId)
            : current_user_can($capability);

        if (!$hasCapability) {
            return new \WP_Error(
                'insufficient_capability',
                sprintf('You do not have permission to %s this resource', $capability),
                ['status' => 403]
            );
        }

        // User has the required capability, allow request to continue
        return null;
    }

    /**
     * Static helper to create middleware with specific capability
     *
     * @param string $capability
     * @return callable
     */
    public static function with($capability)
    {
        return function(RouteRequest $request) use ($capability) {
            $middleware = new static();
            return $middleware->handle($request, $capability);
        };
    }

    /**
     * Static helper to create middleware with capability and object context
     *
     * @param string $capability
     * @param int|null $objectId
     * @return callable
     */
    public static function withObject($capability, $objectId = null)
    {
        return function(RouteRequest $request) use ($capability, $objectId) {
            $middleware = new static();
            return $middleware->handleWithObject($request, $capability, $objectId);
        };
    }
}