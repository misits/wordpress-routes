<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\ApiRequest;

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
     * @param ApiRequest $request
     * @return mixed|null Return null to continue, or response to stop processing
     */
    public function handle(ApiRequest $request);
}