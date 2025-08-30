<?php

namespace WordPressRoutes\Routing\Validation;

use WordPressRoutes\Routing\RouteRequest;

defined("ABSPATH") or exit();

/**
 * Form Request Validation
 * 
 * Laravel-style form request validation for WordPress Routes
 * Allows creating dedicated validation classes for specific endpoints
 */
abstract class FormRequest
{
    protected $request;
    protected $errors = [];

    public function __construct(RouteRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    abstract public function rules();

    /**
     * Get custom error messages
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }

    /**
     * Get custom attribute names
     *
     * @return array
     */
    public function attributes()
    {
        return [];
    }

    /**
     * Determine if the user is authorized to make this request
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Validate the request
     *
     * @return array|WP_Error
     */
    public function validate()
    {
        // Check authorization first
        if (!$this->authorize()) {
            return new \WP_Error('unauthorized', 'This action is unauthorized.', ['status' => 403]);
        }

        // Run validation
        return $this->request->validate($this->rules(), $this->messages(), $this->attributes());
    }

    /**
     * Get validated data
     *
     * @return array
     * @throws \WP_Error
     */
    public function validated()
    {
        $result = $this->validate();
        
        if (is_wp_error($result)) {
            throw $result;
        }
        
        return $result;
    }

    /**
     * Get specific input data
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input($key = null, $default = null)
    {
        return $this->request->input($key, $default);
    }

    /**
     * Get all input data
     *
     * @return array
     */
    public function all()
    {
        return $this->request->all();
    }

    /**
     * Get only specific input keys
     *
     * @param array $keys
     * @return array
     */
    public function only(array $keys)
    {
        return $this->request->only($keys);
    }

    /**
     * Get all input except specific keys
     *
     * @param array $keys
     * @return array
     */
    public function except(array $keys)
    {
        return $this->request->except($keys);
    }

    /**
     * Check if input has a key
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->request->has($key);
    }

    /**
     * Get the authenticated user
     *
     * @return \WP_User|null
     */
    public function user()
    {
        return $this->request->user();
    }

    /**
     * Check if request is authenticated
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return $this->request->isAuthenticated();
    }

    /**
     * Handle a passed validation attempt.
     *
     * @return void
     */
    protected function passedValidation()
    {
        //
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \WP_Error  $errors
     * @return void
     */
    protected function failedValidation(\WP_Error $errors)
    {
        //
    }

    /**
     * Configure the validator instance.
     *
     * @param  \WordPressRoutes\Routing\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator)
    {
        //
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        //
    }
}