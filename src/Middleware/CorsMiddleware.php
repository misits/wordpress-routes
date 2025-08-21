<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * CORS Middleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers for API routes
 * Allows frontend applications to make requests from different domains
 *
 * @since 1.0.0
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Default CORS configuration
     *
     * @var array
     */
    protected static $defaultConfig = [
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => [
            'Accept',
            'Authorization', 
            'Content-Type',
            'X-Requested-With',
            'X-WP-Nonce',
            'Cache-Control',
            'X-API-Key'
        ],
        'exposed_headers' => [],
        'allow_credentials' => true,
        'max_age' => 86400, // 24 hours
    ];

    /**
     * CORS configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Create new CORS middleware instance
     *
     * @param array $config Custom configuration options
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::$defaultConfig, $config);
    }

    /**
     * Handle the middleware request
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    public function handle(RouteRequest $request)
    {
        $origin = $request->header('Origin');
        $method = $request->method();

        // Set CORS headers
        $this->setCorsHeaders($origin, $method);

        // Handle preflight OPTIONS request
        if ($method === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $origin);
        }

        // For actual requests, validate origin if not allowing all
        if (!$this->isOriginAllowed($origin)) {
            return new \WP_Error(
                'cors_origin_not_allowed',
                'CORS: Origin not allowed',
                ['status' => 403]
            );
        }

        // Allow the request to continue
        return null;
    }

    /**
     * Handle preflight OPTIONS request
     *
     * @param RouteRequest $request
     * @param string $origin
     * @return \WP_REST_Response|\WP_Error
     */
    protected function handlePreflightRequest(RouteRequest $request, $origin)
    {
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return new \WP_Error(
                'cors_origin_not_allowed',
                'CORS: Origin not allowed for preflight',
                ['status' => 403]
            );
        }

        $requestMethod = $request->header('Access-Control-Request-Method');
        $requestHeaders = $request->header('Access-Control-Request-Headers');

        // Validate requested method
        if ($requestMethod && !in_array(strtoupper($requestMethod), $this->config['allowed_methods'])) {
            return new \WP_Error(
                'cors_method_not_allowed',
                'CORS: Method not allowed',
                ['status' => 405]
            );
        }

        // Validate requested headers
        if ($requestHeaders) {
            $requestedHeaders = array_map('trim', explode(',', $requestHeaders));
            $allowedHeaders = array_map('strtolower', $this->config['allowed_headers']);
            
            foreach ($requestedHeaders as $header) {
                if (!in_array(strtolower($header), $allowedHeaders)) {
                    return new \WP_Error(
                        'cors_header_not_allowed',
                        "CORS: Header '{$header}' not allowed",
                        ['status' => 403]
                    );
                }
            }
        }

        // Return successful preflight response
        status_header(200);
        exit();
    }

    /**
     * Set CORS headers
     *
     * @param string $origin
     * @param string $method
     */
    protected function setCorsHeaders($origin, $method)
    {
        // Set allowed origin
        if ($this->isOriginAllowed($origin)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        } else {
            header('Access-Control-Allow-Origin: ' . $this->getAllowedOriginsHeader());
        }

        // Set allowed methods
        header('Access-Control-Allow-Methods: ' . implode(', ', $this->config['allowed_methods']));

        // Set allowed headers
        if (!empty($this->config['allowed_headers'])) {
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->config['allowed_headers']));
        }

        // Set exposed headers
        if (!empty($this->config['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $this->config['exposed_headers']));
        }

        // Set credentials
        if ($this->config['allow_credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }

        // Set max age for preflight cache
        if ($method === 'OPTIONS') {
            header('Access-Control-Max-Age: ' . $this->config['max_age']);
        }

        // Prevent caching of CORS responses
        header('Vary: Origin, Access-Control-Request-Method, Access-Control-Request-Headers');
    }

    /**
     * Check if origin is allowed
     *
     * @param string $origin
     * @return bool
     */
    protected function isOriginAllowed($origin)
    {
        // If no origin header, allow (same-origin request)
        if (!$origin) {
            return true;
        }

        $allowedOrigins = $this->config['allowed_origins'];

        // Allow all origins
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        // Check exact match
        if (in_array($origin, $allowedOrigins)) {
            return true;
        }

        // Check wildcard patterns
        foreach ($allowedOrigins as $allowedOrigin) {
            if ($this->matchOriginPattern($origin, $allowedOrigin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match origin against pattern (supports wildcards)
     *
     * @param string $origin
     * @param string $pattern
     * @return bool
     */
    protected function matchOriginPattern($origin, $pattern)
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );

        return preg_match('/^' . $regex . '$/', $origin);
    }

    /**
     * Get the first allowed origin for header
     *
     * @return string
     */
    protected function getAllowedOriginsHeader()
    {
        $allowedOrigins = $this->config['allowed_origins'];
        
        if (in_array('*', $allowedOrigins)) {
            return '*';
        }

        return $allowedOrigins[0] ?? '*';
    }

    /**
     * Static helper to create middleware with custom configuration
     *
     * @param array $config
     * @return callable
     */
    public static function with(array $config = [])
    {
        return function(RouteRequest $request) use ($config) {
            $middleware = new static($config);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for development (allows all origins)
     *
     * @return callable
     */
    public static function allowAll()
    {
        return static::with([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['*'],
            'allowed_headers' => ['*'],
            'allow_credentials' => true,
        ]);
    }

    /**
     * Static helper for production (restrictive)
     *
     * @param array $allowedOrigins
     * @return callable
     */
    public static function production(array $allowedOrigins = [])
    {
        return static::with([
            'allowed_origins' => $allowedOrigins,
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-WP-Nonce'
            ],
            'allow_credentials' => true,
            'max_age' => 3600, // 1 hour
        ]);
    }

    /**
     * Static helper for frontend applications
     *
     * @param array $frontendDomains
     * @return callable
     */
    public static function frontend(array $frontendDomains = [])
    {
        return static::with([
            'allowed_origins' => $frontendDomains,
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => [
                'Accept',
                'Authorization',
                'Content-Type',
                'X-Requested-With',
                'X-WP-Nonce',
                'Cache-Control'
            ],
            'allow_credentials' => true,
            'max_age' => 7200, // 2 hours
        ]);
    }

    /**
     * Set global CORS configuration
     *
     * @param array $config
     */
    public static function setDefaultConfig(array $config)
    {
        self::$defaultConfig = array_merge(self::$defaultConfig, $config);
    }

    /**
     * Get current CORS configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}