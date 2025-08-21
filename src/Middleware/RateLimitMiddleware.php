<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\ApiRequest;

defined("ABSPATH") or exit();

/**
 * Rate Limiting Middleware
 *
 * Limits the number of requests per user/IP within a time window
 *
 * @since 1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * Default rate limit (requests per minute)
     *
     * @var int
     */
    protected $limit = 60;

    /**
     * Time window in seconds
     *
     * @var int
     */
    protected $window = 60;

    /**
     * Create rate limit middleware
     *
     * @param int $limit Requests per window
     * @param int $window Time window in seconds
     */
    public function __construct($limit = 60, $window = 60)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Handle rate limiting
     *
     * @param ApiRequest $request
     * @return \WP_Error|\WP_REST_Response|null
     */
    public function handle(ApiRequest $request)
    {
        $identifier = $this->getIdentifier($request);
        $key = $this->getCacheKey($identifier);
        
        // Get current request count
        $requests = get_transient($key) ?: [];
        $now = time();
        
        // Clean old requests outside the window
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return ($now - $timestamp) < $this->window;
        });
        
        // Check if limit exceeded
        if (count($requests) >= $this->limit) {
            $resetTime = min($requests) + $this->window;
            
            return new \WP_Error(
                'rest_too_many_requests',
                'Too many requests. Please try again later.',
                [
                    'status' => 429,
                    'headers' => [
                        'X-RateLimit-Limit' => $this->limit,
                        'X-RateLimit-Remaining' => 0,
                        'X-RateLimit-Reset' => $resetTime,
                        'Retry-After' => $resetTime - $now,
                    ]
                ]
            );
        }
        
        // Add current request
        $requests[] = $now;
        set_transient($key, $requests, $this->window);
        
        // Add rate limit headers to response
        add_filter('rest_post_dispatch', function($response) use ($requests) {
            if ($response instanceof \WP_REST_Response) {
                $response->header('X-RateLimit-Limit', $this->limit);
                $response->header('X-RateLimit-Remaining', max(0, $this->limit - count($requests)));
            }
            return $response;
        });
        
        return null; // Continue processing
    }

    /**
     * Get identifier for rate limiting (user ID or IP)
     *
     * @param ApiRequest $request
     * @return string
     */
    protected function getIdentifier(ApiRequest $request)
    {
        if ($request->isAuthenticated()) {
            return 'user_' . $request->userId();
        }
        
        return 'ip_' . $request->ip();
    }

    /**
     * Get cache key for rate limit storage
     *
     * @param string $identifier
     * @return string
     */
    protected function getCacheKey($identifier)
    {
        return 'wp_api_rate_limit_' . md5($identifier);
    }

    /**
     * Create rate limit middleware with specific limits
     *
     * @param int $limit
     * @param int $window
     * @return static
     */
    public static function create($limit, $window = 60)
    {
        return new static($limit, $window);
    }
}