<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * Nonce Middleware
 *
 * Validates WordPress nonces for CSRF protection
 * Supports both header-based and parameter-based nonce validation
 *
 * @since 1.0.0
 */
class NonceMiddleware implements MiddlewareInterface
{
    /**
     * Nonce action name
     *
     * @var string
     */
    protected $action;

    /**
     * Nonce parameter name
     *
     * @var string
     */
    protected $parameterName;

    /**
     * Nonce header name
     *
     * @var string
     */
    protected $headerName;

    /**
     * Whether to check both header and parameter
     *
     * @var bool
     */
    protected $checkBoth;

    /**
     * Create new nonce middleware instance
     *
     * @param string $action Nonce action name
     * @param string $parameterName Parameter name to check for nonce
     * @param string $headerName Header name to check for nonce
     * @param bool $checkBoth Whether to check both header and parameter
     */
    public function __construct(
        $action = 'wp_rest',
        $parameterName = '_wpnonce',
        $headerName = 'X-WP-Nonce',
        $checkBoth = false
    ) {
        $this->action = $action;
        $this->parameterName = $parameterName;
        $this->headerName = $headerName;
        $this->checkBoth = $checkBoth;
    }

    /**
     * Handle the middleware request
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    public function handle(RouteRequest $request)
    {
        // Skip nonce check for GET requests (typically safe operations)
        if (strtoupper($request->method()) === 'GET') {
            return null;
        }

        // Skip nonce check if user is not authenticated
        if (!$request->isAuthenticated()) {
            return new \WP_Error(
                'unauthenticated',
                'Authentication required for nonce validation',
                ['status' => 401]
            );
        }

        $nonce = $this->getNonce($request);

        if (!$nonce) {
            return new \WP_Error(
                'missing_nonce',
                sprintf(
                    'Nonce is required. Please provide it via %s header or %s parameter.',
                    $this->headerName,
                    $this->parameterName
                ),
                ['status' => 400]
            );
        }

        // Validate the nonce
        $isValid = wp_verify_nonce($nonce, $this->action);

        if (!$isValid) {
            return new \WP_Error(
                'invalid_nonce',
                'Invalid nonce. Please refresh the page and try again.',
                ['status' => 403]
            );
        }

        // Nonce validation passed
        return null;
    }

    /**
     * Get nonce from request
     *
     * @param RouteRequest $request
     * @return string|null
     */
    protected function getNonce(RouteRequest $request)
    {
        $headerNonce = $request->header($this->headerName);
        $paramNonce = $request->input($this->parameterName);

        if ($this->checkBoth) {
            // Both must be present and match
            if ($headerNonce && $paramNonce && $headerNonce === $paramNonce) {
                return $headerNonce;
            }
            return null;
        }

        // Return first available nonce (prefer header)
        return $headerNonce ?: $paramNonce;
    }

    /**
     * Static helper for REST API nonce validation
     *
     * @return callable
     */
    public static function rest()
    {
        return function(RouteRequest $request) {
            $middleware = new static('wp_rest', '_wpnonce', 'X-WP-Nonce');
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for AJAX nonce validation
     *
     * @param string $action Custom action name
     * @return callable
     */
    public static function ajax($action = 'wp_ajax')
    {
        return function(RouteRequest $request) use ($action) {
            $middleware = new static($action, 'nonce', 'X-WP-Nonce');
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for form submission nonce validation
     *
     * @param string $action Custom action name
     * @return callable
     */
    public static function form($action = 'form_submission')
    {
        return function(RouteRequest $request) use ($action) {
            $middleware = new static($action, '_wpnonce', 'X-WP-Nonce');
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for admin nonce validation
     *
     * @param string $action Admin action name
     * @return callable
     */
    public static function admin($action = 'admin_action')
    {
        return function(RouteRequest $request) use ($action) {
            $middleware = new static($action, '_wpnonce', 'X-WP-Nonce');
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for custom nonce validation
     *
     * @param string $action Nonce action
     * @param string $parameterName Parameter name
     * @param string $headerName Header name
     * @param bool $checkBoth Whether to check both
     * @return callable
     */
    public static function custom($action, $parameterName = '_wpnonce', $headerName = 'X-WP-Nonce', $checkBoth = false)
    {
        return function(RouteRequest $request) use ($action, $parameterName, $headerName, $checkBoth) {
            $middleware = new static($action, $parameterName, $headerName, $checkBoth);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for strict nonce validation (both header and parameter required)
     *
     * @param string $action Nonce action
     * @return callable
     */
    public static function strict($action = 'wp_rest')
    {
        return function(RouteRequest $request) use ($action) {
            $middleware = new static($action, '_wpnonce', 'X-WP-Nonce', true);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper to generate nonce for frontend
     *
     * @param string $action Nonce action
     * @return array
     */
    public static function generateForFrontend($action = 'wp_rest')
    {
        if (!is_user_logged_in()) {
            return [
                'nonce' => null,
                'error' => 'User must be logged in to generate nonce'
            ];
        }

        $nonce = wp_create_nonce($action);

        return [
            'nonce' => $nonce,
            'action' => $action,
            'header_name' => 'X-WP-Nonce',
            'parameter_name' => '_wpnonce',
            'expires' => time() + (12 * HOUR_IN_SECONDS), // WordPress nonces expire after 12-24 hours
            'user_id' => get_current_user_id()
        ];
    }

    /**
     * Static helper to create nonce URL
     *
     * @param string $url Base URL
     * @param string $action Nonce action
     * @param string $parameterName Parameter name
     * @return string
     */
    public static function createUrl($url, $action = 'wp_rest', $parameterName = '_wpnonce')
    {
        $nonce = wp_create_nonce($action);
        $separator = strpos($url, '?') !== false ? '&' : '?';
        
        return $url . $separator . $parameterName . '=' . $nonce;
    }

    /**
     * Static helper to validate nonce directly
     *
     * @param string $nonce Nonce to validate
     * @param string $action Nonce action
     * @return bool|int
     */
    public static function verify($nonce, $action = 'wp_rest')
    {
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Get middleware configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'action' => $this->action,
            'parameter_name' => $this->parameterName,
            'header_name' => $this->headerName,
            'check_both' => $this->checkBoth
        ];
    }

    /**
     * Check if nonce is about to expire
     *
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public static function isExpiringSoon($nonce, $action = 'wp_rest')
    {
        $result = wp_verify_nonce($nonce, $action);
        
        // wp_verify_nonce returns:
        // false if nonce is invalid
        // 1 if nonce is valid and in its first half-life
        // 2 if nonce is valid but in its second half-life (expiring soon)
        
        return $result === 2;
    }

    /**
     * Get nonce lifetime in seconds
     *
     * @return int
     */
    public static function getLifetime()
    {
        // WordPress nonces have a default lifetime of 1 day, but can be filtered
        return apply_filters('nonce_life', DAY_IN_SECONDS);
    }

    /**
     * Refresh nonce if it's expiring soon
     *
     * @param string $nonce Current nonce
     * @param string $action Nonce action
     * @return array
     */
    public static function refreshIfNeeded($nonce, $action = 'wp_rest')
    {
        $result = [
            'refreshed' => false,
            'nonce' => $nonce,
            'action' => $action
        ];

        if (static::isExpiringSoon($nonce, $action)) {
            $result['nonce'] = wp_create_nonce($action);
            $result['refreshed'] = true;
            $result['expires'] = time() + static::getLifetime();
        }

        return $result;
    }
}