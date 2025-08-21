<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * JSON Only Middleware
 *
 * Ensures that requests contain valid JSON content and appropriate headers
 * Useful for API endpoints that only accept JSON data
 *
 * @since 1.0.0
 */
class JsonOnlyMiddleware implements MiddlewareInterface
{
    /**
     * Whether to require Content-Type header
     *
     * @var bool
     */
    protected $requireContentType;

    /**
     * Whether to require valid JSON body for POST/PUT/PATCH requests
     *
     * @var bool
     */
    protected $requireJsonBody;

    /**
     * Allowed HTTP methods that require JSON
     *
     * @var array
     */
    protected $jsonRequiredMethods;

    /**
     * Create new JSON only middleware instance
     *
     * @param bool $requireContentType Whether to require Content-Type header
     * @param bool $requireJsonBody Whether to require valid JSON body
     * @param array $jsonRequiredMethods Methods that require JSON
     */
    public function __construct(
        $requireContentType = true,
        $requireJsonBody = true,
        array $jsonRequiredMethods = ['POST', 'PUT', 'PATCH']
    ) {
        $this->requireContentType = $requireContentType;
        $this->requireJsonBody = $requireJsonBody;
        $this->jsonRequiredMethods = array_map('strtoupper', $jsonRequiredMethods);
    }

    /**
     * Handle the middleware request
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    public function handle(RouteRequest $request)
    {
        $method = strtoupper($request->method());
        $contentType = $request->header('Content-Type');

        // Check if this method requires JSON validation
        if (!in_array($method, $this->jsonRequiredMethods) && $method !== 'GET') {
            // For GET requests and other methods not in the list, just check Accept header
            return $this->validateAcceptHeader($request);
        }

        // Validate Content-Type header
        if ($this->requireContentType && $this->shouldValidateContentType($method)) {
            $error = $this->validateContentType($contentType);
            if ($error) {
                return $error;
            }
        }

        // Validate JSON body for methods that should have one
        if ($this->requireJsonBody && in_array($method, $this->jsonRequiredMethods)) {
            $error = $this->validateJsonBody($request);
            if ($error) {
                return $error;
            }
        }

        // Validate Accept header
        $error = $this->validateAcceptHeader($request);
        if ($error) {
            return $error;
        }

        // All JSON validations passed
        return null;
    }

    /**
     * Check if method should validate Content-Type
     *
     * @param string $method
     * @return bool
     */
    protected function shouldValidateContentType($method)
    {
        return in_array($method, ['POST', 'PUT', 'PATCH']);
    }

    /**
     * Validate Content-Type header
     *
     * @param string $contentType
     * @return \WP_Error|null
     */
    protected function validateContentType($contentType)
    {
        if (!$contentType) {
            return new \WP_Error(
                'missing_content_type',
                'Content-Type header is required for this endpoint',
                ['status' => 400]
            );
        }

        // Parse Content-Type to handle charset and other parameters
        $contentTypeParts = explode(';', $contentType);
        $mainType = trim($contentTypeParts[0]);

        $allowedTypes = [
            'application/json',
            'application/vnd.api+json',
            'text/json'
        ];

        if (!in_array($mainType, $allowedTypes)) {
            return new \WP_Error(
                'invalid_content_type',
                sprintf(
                    'Content-Type must be one of: %s. Received: %s',
                    implode(', ', $allowedTypes),
                    $mainType
                ),
                ['status' => 415] // Unsupported Media Type
            );
        }

        return null;
    }

    /**
     * Validate JSON body
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    protected function validateJsonBody(RouteRequest $request)
    {
        $rawBody = $request->getBody();
        
        // Allow empty body for some cases
        if (empty($rawBody)) {
            return null;
        }

        // Try to decode JSON
        json_decode($rawBody);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'invalid_json',
                sprintf(
                    'Request body contains invalid JSON: %s',
                    $this->getJsonErrorMessage($jsonError)
                ),
                ['status' => 400]
            );
        }

        return null;
    }

    /**
     * Validate Accept header
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    protected function validateAcceptHeader(RouteRequest $request)
    {
        $accept = $request->header('Accept');

        // If no Accept header, assume client accepts JSON
        if (!$accept) {
            return null;
        }

        // Parse Accept header
        $acceptTypes = $this->parseAcceptHeader($accept);
        
        $jsonTypes = [
            'application/json',
            'application/vnd.api+json',
            'text/json',
            '*/*',
            'application/*'
        ];

        // Check if any JSON type is accepted
        foreach ($acceptTypes as $type) {
            if (in_array($type, $jsonTypes)) {
                return null;
            }
        }

        return new \WP_Error(
            'json_not_acceptable',
            'This endpoint only returns JSON. Please include application/json in your Accept header.',
            ['status' => 406] // Not Acceptable
        );
    }

    /**
     * Parse Accept header into array of types
     *
     * @param string $accept
     * @return array
     */
    protected function parseAcceptHeader($accept)
    {
        $types = [];
        $parts = explode(',', $accept);

        foreach ($parts as $part) {
            // Remove quality factor and other parameters
            $type = explode(';', trim($part))[0];
            $types[] = trim($type);
        }

        return $types;
    }

    /**
     * Get human-readable JSON error message
     *
     * @param int $jsonError
     * @return string
     */
    protected function getJsonErrorMessage($jsonError)
    {
        switch ($jsonError) {
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            case JSON_ERROR_RECURSION:
                return 'One or more recursive references in the value to be encoded';
            case JSON_ERROR_INF_OR_NAN:
                return 'One or more NAN or INF values in the value to be encoded';
            case JSON_ERROR_UNSUPPORTED_TYPE:
                return 'A value of a type that cannot be encoded was given';
            default:
                return 'Unknown JSON error';
        }
    }

    /**
     * Static helper to create strict JSON middleware
     *
     * @return callable
     */
    public static function strict()
    {
        return function(RouteRequest $request) {
            $middleware = new static(true, true, ['POST', 'PUT', 'PATCH']);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper to create lenient JSON middleware
     *
     * @return callable
     */
    public static function lenient()
    {
        return function(RouteRequest $request) {
            $middleware = new static(false, false, []);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for API endpoints
     *
     * @return callable
     */
    public static function api()
    {
        return function(RouteRequest $request) {
            $middleware = new static(true, true, ['POST', 'PUT', 'PATCH']);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for form submissions that accept JSON
     *
     * @return callable
     */
    public static function forms()
    {
        return function(RouteRequest $request) {
            $middleware = new static(false, false, ['POST', 'PUT', 'PATCH']);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper with custom configuration
     *
     * @param bool $requireContentType
     * @param bool $requireJsonBody
     * @param array $jsonRequiredMethods
     * @return callable
     */
    public static function custom($requireContentType = true, $requireJsonBody = true, array $jsonRequiredMethods = ['POST', 'PUT', 'PATCH'])
    {
        return function(RouteRequest $request) use ($requireContentType, $requireJsonBody, $jsonRequiredMethods) {
            $middleware = new static($requireContentType, $requireJsonBody, $jsonRequiredMethods);
            return $middleware->handle($request);
        };
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'require_content_type' => $this->requireContentType,
            'require_json_body' => $this->requireJsonBody,
            'json_required_methods' => $this->jsonRequiredMethods
        ];
    }
}