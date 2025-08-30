<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Route Request Wrapper
 *
 * Wraps WordPress REST API request with additional functionality
 * Provides clean interface for accessing request data in routes
 *
 * @since 1.0.0
 */
class RouteRequest
{
    /**
     * WordPress REST request (for API routes)
     *
     * @var \WP_REST_Request|null
     */
    protected $request;

    /**
     * URL parameters (for all route types)
     *
     * @var array
     */
    protected $urlParams = [];

    /**
     * Query parameters (for web/admin routes)
     *
     * @var array
     */
    protected $queryParams = [];

    /**
     * POST data (for web/admin routes)
     *
     * @var array
     */
    protected $postData = [];

    /**
     * Parsed JSON data
     *
     * @var array|null
     */
    protected $json;

    /**
     * Validated data from middleware
     *
     * @var array|null
     */
    protected $validatedData;

    /**
     * Route type (api, web, admin, ajax, webhook)
     *
     * @var string
     */
    protected $routeType = 'api';

    /**
     * Create Route request wrapper
     *
     * @param \WP_REST_Request|null $request WordPress REST request (for API routes)
     * @param array $urlParams URL parameters extracted from route pattern
     * @param string $routeType Type of route (api, web, admin, ajax, webhook)
     */
    public function __construct(?\WP_REST_Request $request = null, array $urlParams = [], string $routeType = 'api')
    {
        $this->request = $request;
        $this->urlParams = $urlParams;
        $this->routeType = $routeType;
        
        // For non-API routes, populate query and post data from globals
        if (!$request && in_array($routeType, ['web', 'admin', 'ajax'])) {
            $this->queryParams = $_GET ?? [];
            $this->postData = $_POST ?? [];
        }
    }

    /**
     * Create RouteRequest for web routes
     *
     * @param array $urlParams URL parameters extracted from route pattern
     * @return static
     */
    public static function createForWeb(array $urlParams = [])
    {
        return new static(null, $urlParams, 'web');
    }

    /**
     * Create RouteRequest for admin routes
     *
     * @param array $urlParams URL parameters extracted from route pattern
     * @return static
     */
    public static function createForAdmin(array $urlParams = [])
    {
        return new static(null, $urlParams, 'admin');
    }

    /**
     * Create RouteRequest for AJAX routes
     *
     * @param array $urlParams URL parameters extracted from route pattern
     * @return static
     */
    public static function createForAjax(array $urlParams = [])
    {
        return new static(null, $urlParams, 'ajax');
    }

    /**
     * Get request method
     *
     * @return string
     */
    public function method()
    {
        return $this->request->get_method();
    }

    /**
     * Get request URL
     *
     * @return string
     */
    public function url()
    {
        return $this->request->get_route();
    }

    /**
     * Get request path
     *
     * @return string
     */
    public function path()
    {
        return parse_url($this->request->get_route(), PHP_URL_PATH);
    }

    /**
     * Get full URL with query parameters
     *
     * @return string
     */
    public function fullUrl()
    {
        global $wp;
        return home_url($wp->request);
    }

    /**
     * Get request input data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return $this->all();
        }

        // For web/admin/ajax routes
        if (!$this->request) {
            // Try POST first, then query params, then URL params
            if (array_key_exists($key, $this->postData)) {
                return $this->postData[$key];
            }
            
            if (array_key_exists($key, $this->queryParams)) {
                return $this->queryParams[$key];
            }
            
            return $this->urlParams[$key] ?? $default;
        }

        // For API routes - try body parameters first
        $body = $this->request->get_json_params() ?: $this->request->get_body_params();
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }

        // Try URL parameters
        $param = $this->request->get_param($key);
        return $param !== null ? $param : $default;
    }

    /**
     * Get all input data
     *
     * @return array
     */
    public function all()
    {
        // For web/admin/ajax routes
        if (!$this->request) {
            return array_merge($this->queryParams, $this->urlParams, $this->postData);
        }

        // For API routes
        $json = $this->request->get_json_params() ?: [];
        $body = $this->request->get_body_params() ?: [];
        $query = $this->request->get_query_params() ?: [];
        $url = $this->request->get_url_params() ?: [];

        return array_merge($query, $url, $body, $json);
    }

    /**
     * Get only specified input keys
     *
     * @param array $keys
     * @return array
     */
    public function only(array $keys)
    {
        $data = $this->all();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Get all input except specified keys
     *
     * @param array $keys
     * @return array
     */
    public function except(array $keys)
    {
        $data = $this->all();
        return array_diff_key($data, array_flip($keys));
    }

    /**
     * Check if input has a key
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        $data = $this->all();
        return array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '';
    }

    /**
     * Check if input is filled (not empty)
     *
     * @param string $key
     * @return bool
     */
    public function filled($key)
    {
        return $this->has($key) && !empty($this->input($key));
    }

    /**
     * Get route parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function param($key, $default = null)
    {
        // For web/admin/ajax routes, use urlParams
        if (!$this->request) {
            return $this->urlParams[$key] ?? $default;
        }
        
        // For API routes, use WordPress REST API params
        $params = $this->request->get_url_params();
        return $params[$key] ?? $default;
    }

    /**
     * Get all URL parameters
     *
     * @return array
     */
    public function params()
    {
        // For web/admin/ajax routes, use urlParams
        if (!$this->request) {
            return $this->urlParams;
        }
        
        // For API routes, use WordPress REST API params
        return $this->request->get_url_params();
    }

    /**
     * Get query parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        // For web/admin/ajax routes, use queryParams
        if (!$this->request) {
            if ($key === null) {
                return $this->queryParams;
            }
            return $this->queryParams[$key] ?? $default;
        }
        
        // For API routes, use WordPress REST API query params
        $params = $this->request->get_query_params();
        
        if ($key === null) {
            return $params;
        }
        
        return $params[$key] ?? $default;
    }

    /**
     * Get JSON data from request body
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function json($key = null, $default = null)
    {
        if ($this->json === null) {
            $this->json = $this->request->get_json_params() ?: [];
        }

        if ($key === null) {
            return $this->json;
        }

        return $this->json[$key] ?? $default;
    }

    /**
     * Get header value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function header($key, $default = null)
    {
        return $this->request->get_header($key) ?: $default;
    }

    /**
     * Get all headers
     *
     * @return array
     */
    public function headers()
    {
        return $this->request->get_headers();
    }

    /**
     * Check if request has file
     *
     * @param string $key
     * @return bool
     */
    public function hasFile($key)
    {
        $files = $this->request->get_file_params();
        return isset($files[$key]) && is_uploaded_file($files[$key]['tmp_name']);
    }

    /**
     * Get uploaded file
     *
     * @param string $key
     * @return array|null
     */
    public function file($key)
    {
        $files = $this->request->get_file_params();
        return $files[$key] ?? null;
    }

    /**
     * Get all uploaded files
     *
     * @return array
     */
    public function allFiles()
    {
        return $this->request->get_file_params();
    }

    /**
     * Get content type
     *
     * @return string|null
     */
    public function contentType()
    {
        return $this->request->get_content_type();
    }

    /**
     * Check if request accepts JSON
     *
     * @return bool
     */
    public function wantsJson()
    {
        $acceptable = $this->request->get_header('accept');
        return $acceptable && strpos($acceptable, 'application/json') !== false;
    }

    /**
     * Check if request is JSON
     *
     * @return bool
     */
    public function isJson()
    {
        $contentType = $this->contentType();
        return $contentType && strpos($contentType['value'], 'application/json') !== false;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function ip()
    {
        return $this->request->get_header('x-forwarded-for') ?: 
               $this->request->get_header('x-real-ip') ?: 
               $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public function userAgent()
    {
        return $this->request->get_header('user-agent') ?: '';
    }

    /**
     * Get current user
     *
     * @return \WP_User|null
     */
    public function user()
    {
        $user_id = get_current_user_id();
        return $user_id ? get_userdata($user_id) : null;
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    public function userId()
    {
        return get_current_user_id();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        // For API routes with WordPress REST request
        if ($this->request) {
            // Check if user is logged in (works with cookie auth and application passwords)
            if (is_user_logged_in()) {
                return true;
            }
            
            // Check for nonce-based authentication (WordPress default for logged-in users)
            $nonce = $this->request->get_header('X-WP-Nonce') ?: 
                     $this->request->get_header('x-wp-nonce') ?: 
                     $this->request->get_param('_wpnonce');
            if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
                return true;
            }
            
            // Check if current user was determined by WordPress REST API
            $current_user_id = get_current_user_id();
            return $current_user_id > 0;
        }
        
        // For web/admin/ajax routes without WordPress REST request
        return is_user_logged_in();
    }

    /**
     * Check if user has capability
     *
     * @param string $capability
     * @return bool
     */
    public function userCan($capability)
    {
        return current_user_can($capability);
    }

    /**
     * Get nonce from request
     *
     * @param string $field
     * @return string|null
     */
    public function nonce($field = '_wpnonce')
    {
        return $this->input($field);
    }

    /**
     * Verify nonce
     *
     * @param string $action
     * @param string $field
     * @return bool
     */
    public function verifyNonce($action, $field = '_wpnonce')
    {
        $nonce = $this->nonce($field);
        return $nonce && wp_verify_nonce($nonce, $action);
    }

    /**
     * Validate request data using enhanced Laravel-style validation
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @param array $attributes Custom attribute names
     * @return array|WP_Error
     */
    public function validate(array $rules, array $messages = [], array $attributes = [])
    {
        $validator = new \WordPressRoutes\Routing\Validation\Validator(
            $this->all(),
            $rules,
            $messages,
            $attributes
        );

        return $validator->validate();
    }

    /**
     * Create a validator instance without running validation
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @param array $attributes Custom attribute names
     * @return \WordPressRoutes\Routing\Validation\Validator
     */
    public function validator(array $rules, array $messages = [], array $attributes = [])
    {
        return new \WordPressRoutes\Routing\Validation\Validator(
            $this->all(),
            $rules,
            $messages,
            $attributes
        );
    }

    /**
     * Check if validation passes
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @param array $attributes Custom attribute names
     * @return bool
     */
    public function passes(array $rules, array $messages = [], array $attributes = [])
    {
        return $this->validator($rules, $messages, $attributes)->passes();
    }

    /**
     * Check if validation fails
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @param array $attributes Custom attribute names
     * @return bool
     */
    public function fails(array $rules, array $messages = [], array $attributes = [])
    {
        return $this->validator($rules, $messages, $attributes)->fails();
    }

    /**
     * Get the underlying WordPress REST request
     *
     * @return \WP_REST_Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set request data (for testing)
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        foreach ($data as $key => $value) {
            $this->request->set_param($key, $value);
        }
        return $this;
    }

    /**
     * Merge additional data into request
     *
     * @param array $data
     * @return $this
     */
    public function merge(array $data)
    {
        return $this->setData($data);
    }

    /**
     * Get raw request body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->request->get_body();
    }

    /**
     * Set validated data from middleware
     *
     * @param array $data
     * @return $this
     */
    public function setValidatedData(array $data)
    {
        $this->validatedData = $data;
        return $this;
    }

    /**
     * Get validated data from middleware
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        if ($key === null) {
            return $this->validatedData ?? [];
        }

        return $this->validatedData[$key] ?? $default;
    }

    /**
     * Check if request has validated data
     *
     * @return bool
     */
    public function hasValidatedData()
    {
        return !empty($this->validatedData);
    }
}