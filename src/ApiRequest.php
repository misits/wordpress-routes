<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * API Request Wrapper
 *
 * Wraps WordPress REST API request with additional functionality
 * Provides clean interface for accessing request data in API routes
 *
 * @since 1.0.0
 */
class ApiRequest
{
    /**
     * WordPress REST request
     *
     * @var \WP_REST_Request
     */
    protected $request;

    /**
     * Parsed JSON data
     *
     * @var array|null
     */
    protected $json;

    /**
     * Create API request wrapper
     *
     * @param \WP_REST_Request $request
     */
    public function __construct(\WP_REST_Request $request)
    {
        $this->request = $request;
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
     * Check if request method matches
     *
     * @param string $method
     * @return bool
     */
    public function isMethod($method)
    {
        return strtoupper($this->method()) === strtoupper($method);
    }

    /**
     * Get request route
     *
     * @return string
     */
    public function route()
    {
        return $this->request->get_route();
    }

    /**
     * Get URL parameter from route
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function param($key, $default = null)
    {
        return $this->request->get_url_params()[$key] ?? $default;
    }

    /**
     * Get all URL parameters
     *
     * @return array
     */
    public function params()
    {
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
        if ($key === null) {
            return $this->request->get_query_params();
        }

        return $this->request->get_query_params()[$key] ?? $default;
    }

    /**
     * Get body parameter (POST data)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return array_merge(
                $this->request->get_query_params(),
                $this->request->get_body_params()
            );
        }

        $params = $this->request->get_param($key);
        return $params !== null ? $params : $default;
    }

    /**
     * Get all input data (query + body)
     *
     * @return array
     */
    public function all()
    {
        return array_merge(
            $this->request->get_query_params(),
            $this->request->get_body_params()
        );
    }

    /**
     * Get only specified keys from input
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
     * Get input except specified keys
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
     * Check if input has key
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->request->has_param($key);
    }

    /**
     * Get request header
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
     * Get raw request body
     *
     * @return string
     */
    public function body()
    {
        return $this->request->get_body();
    }

    /**
     * Get JSON data from body
     *
     * @param bool $assoc
     * @return mixed|null
     */
    public function json($assoc = true)
    {
        if ($this->json === null && $this->isJson()) {
            $this->json = json_decode($this->body(), $assoc);
        }

        return $this->json;
    }

    /**
     * Check if request contains JSON
     *
     * @return bool
     */
    public function isJson()
    {
        $contentType = $this->header('content-type', '');
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * Check if request is AJAX
     *
     * @return bool
     */
    public function isAjax()
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest';
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
    public function files()
    {
        return $this->request->get_file_params();
    }

    /**
     * Check if request has file
     *
     * @param string $key
     * @return bool
     */
    public function hasFile($key)
    {
        $file = $this->file($key);
        return $file && $file['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function ip()
    {
        // Check for various IP headers
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public function userAgent()
    {
        return $this->header('user-agent', '');
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
     * Get current user
     *
     * @return \WP_User|false
     */
    public function user()
    {
        return wp_get_current_user();
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
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
     * Validate request data
     *
     * @param array $rules
     * @return array|WP_Error
     */
    public function validate(array $rules)
    {
        $errors = [];
        $data = $this->all();

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldRules = is_string($rule) ? explode('|', $rule) : (array) $rule;

            foreach ($fieldRules as $singleRule) {
                if ($singleRule === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "The {$field} field is required.";
                    continue;
                }

                if ($singleRule === 'email' && $value && !is_email($value)) {
                    $errors[$field][] = "The {$field} field must be a valid email address.";
                }

                if (strpos($singleRule, 'min:') === 0) {
                    $min = (int) substr($singleRule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "The {$field} field must be at least {$min} characters.";
                    }
                }

                if (strpos($singleRule, 'max:') === 0) {
                    $max = (int) substr($singleRule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "The {$field} field may not be greater than {$max} characters.";
                    }
                }

                if ($singleRule === 'numeric' && $value && !is_numeric($value)) {
                    $errors[$field][] = "The {$field} field must be a number.";
                }
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', 'Validation failed', ['errors' => $errors, 'status' => 422]);
        }

        return $data;
    }

    /**
     * Get the underlying WordPress REST request
     *
     * @return \WP_REST_Request
     */
    public function getWpRequest()
    {
        return $this->request;
    }

    /**
     * Magic method to forward calls to WordPress request
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->request, $method)) {
            return call_user_func_array([$this->request, $method], $arguments);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on ApiRequest");
    }
}