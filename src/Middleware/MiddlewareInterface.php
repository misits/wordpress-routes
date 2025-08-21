<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * Middleware Interface
 *
 * All middleware should implement this interface
 * Provides consistent structure for middleware classes
 *
 * @since 1.0.0
 */
interface MiddlewareInterface
{
    /**
     * Handle the request
     *
     * @param RouteRequest $request
     * @return mixed|null Return null to continue, or response to stop processing
     */
    public function handle(RouteRequest $request);
}