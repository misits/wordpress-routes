<?php

namespace WordPressRoutes\Routing\Middleware;

use WordPressRoutes\Routing\RouteRequest;
use WordPressRoutes\Routing\Validation\Validator;

defined("ABSPATH") or exit();

/**
 * Validation Middleware
 *
 * Validates incoming request data against defined rules
 * Uses the built-in validation system for comprehensive data validation
 *
 * @since 1.0.0
 */
class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * Validation rules
     *
     * @var array
     */
    protected $rules;

    /**
     * Custom validation messages
     *
     * @var array
     */
    protected $messages;

    /**
     * Create new validation middleware instance
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     */
    public function __construct(array $rules = [], array $messages = [])
    {
        $this->rules = $rules;
        $this->messages = $messages;
    }

    /**
     * Handle the middleware request
     *
     * @param RouteRequest $request
     * @return \WP_Error|null
     */
    public function handle(RouteRequest $request)
    {
        // If no rules are set, validation passes
        if (empty($this->rules)) {
            return null;
        }

        // Get request data based on HTTP method
        $data = $this->getRequestData($request);

        // Create validator instance
        $validator = new Validator($data, $this->rules, $this->messages);

        // Validate the data
        $result = $validator->validate();

        // If validation failed, return the error
        if (is_wp_error($result)) {
            return $result;
        }

        // Store validated data in request for later use
        $request->setValidatedData($result);

        // Validation passed
        return null;
    }

    /**
     * Get request data for validation
     *
     * @param RouteRequest $request
     * @return array
     */
    protected function getRequestData(RouteRequest $request)
    {
        $method = strtoupper($request->method());

        switch ($method) {
            case 'GET':
                return $request->query();
            
            case 'POST':
            case 'PUT':
            case 'PATCH':
                // Merge JSON body data with form data
                $bodyData = $request->json() ?: [];
                $formData = $request->input() ?: [];
                return array_merge($formData, $bodyData);
            
            case 'DELETE':
                // For DELETE, check both query params and body
                $queryData = $request->query() ?: [];
                $bodyData = $request->json() ?: [];
                return array_merge($queryData, $bodyData);
            
            default:
                return $request->all();
        }
    }

    /**
     * Static helper to create middleware with rules
     *
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return callable
     */
    public static function rules(array $rules, array $messages = [])
    {
        return function(RouteRequest $request) use ($rules, $messages) {
            $middleware = new static($rules, $messages);
            return $middleware->handle($request);
        };
    }

    /**
     * Static helper for common validation scenarios
     *
     * @return callable
     */
    public static function json()
    {
        return static::rules([
            'Content-Type' => 'required|contains:application/json'
        ], [
            'Content-Type.required' => 'Content-Type header is required',
            'Content-Type.contains' => 'Content-Type must be application/json'
        ]);
    }

    /**
     * Static helper for user creation validation
     *
     * @return callable
     */
    public static function userCreate()
    {
        return static::rules([
            'username' => 'required|string|min:3|max:60|unique:users,user_login',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|same:password',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:subscriber,contributor,author,editor,administrator'
        ]);
    }

    /**
     * Static helper for user update validation
     *
     * @param int $userId User ID to exclude from unique checks
     * @return callable
     */
    public static function userUpdate($userId = null)
    {
        $rules = [
            'email' => 'sometimes|email',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'role' => 'sometimes|string|in:subscriber,contributor,author,editor,administrator',
            'password' => 'sometimes|string|min:8',
            'password_confirmation' => 'required_with:password|same:password'
        ];

        if ($userId) {
            $rules['email'] .= "|unique:users,user_email,{$userId}";
        }

        return static::rules($rules);
    }

    /**
     * Static helper for post creation validation
     *
     * @return callable
     */
    public static function postCreate()
    {
        return static::rules([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'status' => 'sometimes|string|in:draft,publish,private,pending',
            'excerpt' => 'sometimes|string|max:500',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:terms,term_id',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:255',
            'featured_image' => 'sometimes|integer|exists:posts,ID',
            'meta' => 'sometimes|array'
        ]);
    }

    /**
     * Static helper for post update validation
     *
     * @return callable
     */
    public static function postUpdate()
    {
        return static::rules([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'status' => 'sometimes|string|in:draft,publish,private,pending,trash',
            'excerpt' => 'sometimes|string|max:500',
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:terms,term_id',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:255',
            'featured_image' => 'sometimes|integer|exists:posts,ID',
            'meta' => 'sometimes|array'
        ]);
    }

    /**
     * Static helper for search validation
     *
     * @return callable
     */
    public static function search()
    {
        return static::rules([
            'q' => 'required|string|min:2|max:255',
            'post_type' => 'sometimes|string|in:post,page,any',
            'posts_per_page' => 'sometimes|integer|min:1|max:100',
            'offset' => 'sometimes|integer|min:0',
            'orderby' => 'sometimes|string|in:date,title,menu_order,relevance',
            'order' => 'sometimes|string|in:ASC,DESC'
        ]);
    }

    /**
     * Static helper for pagination validation
     *
     * @return callable
     */
    public static function pagination()
    {
        return static::rules([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'orderby' => 'sometimes|string',
            'order' => 'sometimes|string|in:ASC,DESC'
        ]);
    }

    /**
     * Static helper for file upload validation
     *
     * @param array $allowedTypes Allowed file types
     * @param int $maxSize Max file size in MB
     * @return callable
     */
    public static function fileUpload(array $allowedTypes = [], $maxSize = 10)
    {
        $rules = [
            'file' => 'required|file',
        ];

        if (!empty($allowedTypes)) {
            $rules['file'] .= '|mimes:' . implode(',', $allowedTypes);
        }

        if ($maxSize > 0) {
            $rules['file'] .= "|max:{$maxSize}MB";
        }

        return static::rules($rules);
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Get validation messages
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Set validation rules
     *
     * @param array $rules
     * @return self
     */
    public function setRules(array $rules)
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Set validation messages
     *
     * @param array $messages
     * @return self
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Add validation rule
     *
     * @param string $field
     * @param string $rule
     * @return self
     */
    public function addRule($field, $rule)
    {
        $this->rules[$field] = $rule;
        return $this;
    }

    /**
     * Add validation message
     *
     * @param string $key
     * @param string $message
     * @return self
     */
    public function addMessage($key, $message)
    {
        $this->messages[$key] = $message;
        return $this;
    }
}