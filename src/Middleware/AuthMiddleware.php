<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\ApiRequest;

defined("ABSPATH") or exit();

/**
 * Authentication Middleware
 *
 * Requires user to be logged in to access the route
 *
 * @since 1.0.0
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Handle authentication check
     *
     * @param ApiRequest $request
     * @return \WP_Error|null
     */
    public function handle(ApiRequest $request)
    {
        if (!$request->isAuthenticated()) {
            return new \WP_Error(
                'rest_not_logged_in',
                'You are not currently logged in.',
                ['status' => 401]
            );
        }

        return null; // Continue processing
    }
}