<?php

namespace WordPressRoutes\Routing\Validation;

defined("ABSPATH") or exit();

/**
 * Enhanced Validation System
 * 
 * Laravel-inspired validation with WordPress integration
 * Provides comprehensive validation rules and features
 */
class Validator
{
    protected $data;
    protected $rules;
    protected $errors = [];
    protected $customMessages = [];
    protected $customAttributes = [];

    public function __construct(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $messages;
        $this->customAttributes = $attributes;
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        return new static($data, $rules, $messages, $attributes);
    }

    public function validate()
    {
        foreach ($this->rules as $field => $rule) {
            $this->validateField($field, $rule);
        }

        if (!empty($this->errors)) {
            return new \WP_Error('validation_failed', 'Validation failed', [
                'errors' => $this->errors,
                'status' => 422
            ]);
        }

        return $this->data;
    }

    public function fails()
    {
        $this->validate();
        return !empty($this->errors);
    }

    public function passes()
    {
        return !$this->fails();
    }

    public function errors()
    {
        return $this->errors;
    }

    protected function validateField($field, $rules)
    {
        $value = $this->getValue($field);
        $ruleArray = is_string($rules) ? explode('|', $rules) : (array) $rules;

        foreach ($ruleArray as $rule) {
            // Skip validation if nullable and value is null
            if ($rule === 'nullable' && $value === null) {
                continue;
            }

            $this->validateRule($field, $value, $rule);
        }
    }

    protected function getValue($field)
    {
        // Support dot notation for nested arrays
        return data_get($this->data, $field);
    }

    protected function validateRule($field, $value, $rule)
    {
        if (strpos($rule, ':') !== false) {
            [$ruleName, $parameter] = explode(':', $rule, 2);
            $parameters = str_contains($parameter, ',') ? explode(',', $parameter) : [$parameter];
        } else {
            $ruleName = $rule;
            $parameters = [];
        }

        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            $result = $this->$method($field, $value, $parameters);
            if ($result === false) {
                $this->addError($field, $ruleName, $parameters);
            }
        }
    }

    protected function addError($field, $rule, $parameters = [])
    {
        $message = $this->getErrorMessage($field, $rule, $parameters);
        $this->errors[$field][] = $message;
    }

    protected function getErrorMessage($field, $rule, $parameters = [])
    {
        $key = "{$field}.{$rule}";
        $attribute = $this->customAttributes[$field] ?? str_replace('_', ' ', $field);

        // Check for custom message
        if (isset($this->customMessages[$key])) {
            return str_replace([':attribute', ':value'], [$attribute, $this->getValue($field)], $this->customMessages[$key]);
        }

        // Default messages
        return $this->getDefaultMessage($attribute, $rule, $parameters);
    }

    protected function getDefaultMessage($attribute, $rule, $parameters = [])
    {
        $messages = [
            'accepted' => "The {$attribute} field must be accepted.",
            'accepted_if' => "The {$attribute} field must be accepted when " . ($parameters[0] ?? 'condition') . " is " . ($parameters[1] ?? 'value') . ".",
            'after' => "The {$attribute} field must be a date after " . ($parameters[0] ?? 'date') . ".",
            'after_or_equal' => "The {$attribute} field must be a date after or equal to " . ($parameters[0] ?? 'date') . ".",
            'alpha' => "The {$attribute} field must contain only letters.",
            'alpha_dash' => "The {$attribute} field must contain only letters, numbers, dashes, and underscores.",
            'alpha_num' => "The {$attribute} field must contain only letters and numbers.",
            'array' => "The {$attribute} field must be an array.",
            'ascii' => "The {$attribute} field must contain only single-byte alphanumeric characters and symbols.",
            'before' => "The {$attribute} field must be a date before " . ($parameters[0] ?? 'date') . ".",
            'before_or_equal' => "The {$attribute} field must be a date before or equal to " . ($parameters[0] ?? 'date') . ".",
            'between' => "The {$attribute} field must be between " . ($parameters[0] ?? 'min') . " and " . ($parameters[1] ?? 'max') . ".",
            'boolean' => "The {$attribute} field must be true or false.",
            'confirmed' => "The {$attribute} field confirmation does not match.",
            'date' => "The {$attribute} field must be a valid date.",
            'date_equals' => "The {$attribute} field must be a date equal to " . ($parameters[0] ?? 'date') . ".",
            'date_format' => "The {$attribute} field must match the format " . ($parameters[0] ?? 'format') . ".",
            'decimal' => "The {$attribute} field must have " . ($parameters[0] ?? 'N') . " decimal places.",
            'declined' => "The {$attribute} field must be declined.",
            'declined_if' => "The {$attribute} field must be declined when " . ($parameters[0] ?? 'condition') . " is " . ($parameters[1] ?? 'value') . ".",
            'different' => "The {$attribute} field and " . ($parameters[0] ?? 'other') . " must be different.",
            'digits' => "The {$attribute} field must be " . ($parameters[0] ?? 'N') . " digits.",
            'digits_between' => "The {$attribute} field must be between " . ($parameters[0] ?? 'min') . " and " . ($parameters[1] ?? 'max') . " digits.",
            'dimensions' => "The {$attribute} field has invalid image dimensions.",
            'distinct' => "The {$attribute} field has a duplicate value.",
            'email' => "The {$attribute} field must be a valid email address.",
            'ends_with' => "The {$attribute} field must end with one of the following: " . implode(', ', $parameters),
            'exists' => "The selected {$attribute} is invalid.",
            'file' => "The {$attribute} field must be a file.",
            'filled' => "The {$attribute} field must have a value.",
            'gt' => "The {$attribute} field must be greater than " . ($parameters[0] ?? 'value') . ".",
            'gte' => "The {$attribute} field must be greater than or equal to " . ($parameters[0] ?? 'value') . ".",
            'image' => "The {$attribute} field must be an image.",
            'in' => "The selected {$attribute} is invalid.",
            'in_array' => "The {$attribute} field does not exist in " . ($parameters[0] ?? 'list') . ".",
            'integer' => "The {$attribute} field must be an integer.",
            'ip' => "The {$attribute} field must be a valid IP address.",
            'ipv4' => "The {$attribute} field must be a valid IPv4 address.",
            'ipv6' => "The {$attribute} field must be a valid IPv6 address.",
            'json' => "The {$attribute} field must be a valid JSON string.",
            'lowercase' => "The {$attribute} field must be lowercase.",
            'lt' => "The {$attribute} field must be less than " . ($parameters[0] ?? 'value') . ".",
            'lte' => "The {$attribute} field must be less than or equal to " . ($parameters[0] ?? 'value') . ".",
            'max' => "The {$attribute} field must not be greater than " . ($parameters[0] ?? 'max') . ".",
            'mimes' => "The {$attribute} field must be a file of type: " . implode(', ', $parameters),
            'min' => "The {$attribute} field must be at least " . ($parameters[0] ?? 'min') . ".",
            'multiple_of' => "The {$attribute} field must be a multiple of " . ($parameters[0] ?? 'value') . ".",
            'not_in' => "The selected {$attribute} is invalid.",
            'not_regex' => "The {$attribute} field format is invalid.",
            'numeric' => "The {$attribute} field must be a number.",
            'present' => "The {$attribute} field must be present.",
            'regex' => "The {$attribute} field format is invalid.",
            'required' => "The {$attribute} field is required.",
            'required_if' => "The {$attribute} field is required when " . ($parameters[0] ?? 'field') . " is " . ($parameters[1] ?? 'value') . ".",
            'required_unless' => "The {$attribute} field is required unless " . ($parameters[0] ?? 'field') . " is in " . implode(', ', array_slice($parameters, 1)),
            'required_with' => "The {$attribute} field is required when " . implode(' / ', $parameters) . " is present.",
            'required_with_all' => "The {$attribute} field is required when " . implode(' / ', $parameters) . " are present.",
            'required_without' => "The {$attribute} field is required when " . implode(' / ', $parameters) . " is not present.",
            'required_without_all' => "The {$attribute} field is required when none of " . implode(' / ', $parameters) . " are present.",
            'same' => "The {$attribute} field and " . ($parameters[0] ?? 'other') . " must match.",
            'size' => "The {$attribute} field must be " . ($parameters[0] ?? 'size') . ".",
            'starts_with' => "The {$attribute} field must start with one of the following: " . implode(', ', $parameters),
            'string' => "The {$attribute} field must be a string.",
            'timezone' => "The {$attribute} field must be a valid timezone.",
            'unique' => "The {$attribute} has already been taken.",
            'uploaded' => "The {$attribute} failed to upload.",
            'uppercase' => "The {$attribute} field must be uppercase.",
            'url' => "The {$attribute} field must be a valid URL.",
            'uuid' => "The {$attribute} field must be a valid UUID.",
        ];

        return $messages[$rule] ?? "The {$attribute} field is invalid.";
    }

    // Validation Rules Implementation

    protected function validateRequired($field, $value, $parameters)
    {
        return !($value === null || $value === '' || $value === []);
    }

    protected function validateEmail($field, $value, $parameters)
    {
        return $value === null || is_email($value);
    }

    protected function validateMin($field, $value, $parameters)
    {
        $min = (int) $parameters[0];
        if (is_string($value)) {
            return strlen($value) >= $min;
        }
        if (is_numeric($value)) {
            return $value >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    protected function validateMax($field, $value, $parameters)
    {
        $max = (int) $parameters[0];
        if (is_string($value)) {
            return strlen($value) <= $max;
        }
        if (is_numeric($value)) {
            return $value <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    protected function validateNumeric($field, $value, $parameters)
    {
        return $value === null || is_numeric($value);
    }

    protected function validateInteger($field, $value, $parameters)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    protected function validateString($field, $value, $parameters)
    {
        return $value === null || is_string($value);
    }

    protected function validateBoolean($field, $value, $parameters)
    {
        $acceptable = [true, false, 0, 1, '0', '1', 'true', 'false'];
        return $value === null || in_array($value, $acceptable, true);
    }

    protected function validateArray($field, $value, $parameters)
    {
        return $value === null || is_array($value);
    }

    protected function validateAlpha($field, $value, $parameters)
    {
        return $value === null || preg_match('/^[\pL\pM]+$/u', $value);
    }

    protected function validateAlphaNum($field, $value, $parameters)
    {
        return $value === null || preg_match('/^[\pL\pM\pN]+$/u', $value);
    }

    protected function validateAlphaDash($field, $value, $parameters)
    {
        return $value === null || preg_match('/^[\pL\pM\pN_-]+$/u', $value);
    }

    protected function validateUrl($field, $value, $parameters)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function validateIp($field, $value, $parameters)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    protected function validateIpv4($field, $value, $parameters)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    protected function validateIpv6($field, $value, $parameters)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    protected function validateJson($field, $value, $parameters)
    {
        if ($value === null) return true;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    protected function validateRegex($field, $value, $parameters)
    {
        return $value === null || preg_match($parameters[0], $value);
    }

    protected function validateIn($field, $value, $parameters)
    {
        return $value === null || in_array((string) $value, $parameters);
    }

    protected function validateNotIn($field, $value, $parameters)
    {
        return $value === null || !in_array((string) $value, $parameters);
    }

    protected function validateBetween($field, $value, $parameters)
    {
        $min = $parameters[0];
        $max = $parameters[1];
        
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        
        if (is_string($value)) {
            $length = strlen($value);
            return $length >= $min && $length <= $max;
        }
        
        return false;
    }

    protected function validateSize($field, $value, $parameters)
    {
        $size = (int) $parameters[0];
        
        if (is_numeric($value)) {
            return $value == $size;
        }
        
        if (is_string($value)) {
            return strlen($value) == $size;
        }
        
        if (is_array($value)) {
            return count($value) == $size;
        }
        
        return false;
    }

    protected function validateDigits($field, $value, $parameters)
    {
        $digits = (int) $parameters[0];
        return $value === null || (is_numeric($value) && strlen((string) $value) == $digits);
    }

    protected function validateDigitsBetween($field, $value, $parameters)
    {
        $min = (int) $parameters[0];
        $max = (int) $parameters[1];
        $length = strlen((string) $value);
        return $value === null || (is_numeric($value) && $length >= $min && $length <= $max);
    }

    protected function validateConfirmed($field, $value, $parameters)
    {
        $confirmField = $field . '_confirmation';
        return $value === data_get($this->data, $confirmField);
    }

    protected function validateSame($field, $value, $parameters)
    {
        $other = $parameters[0];
        return $value === data_get($this->data, $other);
    }

    protected function validateDifferent($field, $value, $parameters)
    {
        $other = $parameters[0];
        return $value !== data_get($this->data, $other);
    }

    protected function validateDate($field, $value, $parameters)
    {
        if ($value === null) return true;
        return strtotime($value) !== false;
    }

    protected function validateDateFormat($field, $value, $parameters)
    {
        if ($value === null) return true;
        $format = $parameters[0];
        $date = \DateTime::createFromFormat($format, $value);
        return $date && $date->format($format) === $value;
    }

    protected function validateRequiredIf($field, $value, $parameters)
    {
        $other = $parameters[0];
        $expectedValue = $parameters[1];
        $otherValue = data_get($this->data, $other);
        
        if ($otherValue == $expectedValue) {
            return $this->validateRequired($field, $value, []);
        }
        
        return true;
    }

    protected function validateRequiredWith($field, $value, $parameters)
    {
        foreach ($parameters as $param) {
            if (data_get($this->data, $param) !== null) {
                return $this->validateRequired($field, $value, []);
            }
        }
        return true;
    }

    protected function validateRequiredWithAll($field, $value, $parameters)
    {
        foreach ($parameters as $param) {
            if (data_get($this->data, $param) === null) {
                return true;
            }
        }
        return $this->validateRequired($field, $value, []);
    }

    protected function validateRequiredWithout($field, $value, $parameters)
    {
        foreach ($parameters as $param) {
            if (data_get($this->data, $param) === null) {
                return $this->validateRequired($field, $value, []);
            }
        }
        return true;
    }

    protected function validateRequiredWithoutAll($field, $value, $parameters)
    {
        foreach ($parameters as $param) {
            if (data_get($this->data, $param) !== null) {
                return true;
            }
        }
        return $this->validateRequired($field, $value, []);
    }

    protected function validateExists($field, $value, $parameters)
    {
        if ($value === null) return true;
        
        global $wpdb;
        $table = $parameters[0];
        $column = $parameters[1] ?? 'id';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s",
            $value
        ));
        
        return $count > 0;
    }

    protected function validateUnique($field, $value, $parameters)
    {
        if ($value === null) return true;
        
        global $wpdb;
        $table = $parameters[0];
        $column = $parameters[1] ?? $field;
        $ignore = $parameters[2] ?? null;
        
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = %s";
        $params = [$value];
        
        if ($ignore) {
            $query .= " AND id != %s";
            $params[] = $ignore;
        }
        
        $count = $wpdb->get_var($wpdb->prepare($query, ...$params));
        
        return $count == 0;
    }

    // WordPress-specific validation rules

    protected function validateUserExists($field, $value, $parameters)
    {
        return $value === null || get_user_by('ID', $value) !== false;
    }

    protected function validatePostExists($field, $value, $parameters)
    {
        return $value === null || get_post($value) !== null;
    }

    protected function validateTermExists($field, $value, $parameters)
    {
        return $value === null || get_term($value) !== null && !is_wp_error(get_term($value));
    }

    protected function validateCapability($field, $value, $parameters)
    {
        return $value === null || current_user_can($value);
    }

    protected function validatePostType($field, $value, $parameters)
    {
        return $value === null || post_type_exists($value);
    }

    protected function validateTaxonomy($field, $value, $parameters)
    {
        return $value === null || taxonomy_exists($value);
    }

    protected function validateUserRole($field, $value, $parameters)
    {
        global $wp_roles;
        return $value === null || isset($wp_roles->roles[$value]);
    }

    protected function validateSlug($field, $value, $parameters)
    {
        return $value === null || preg_match('/^[a-z0-9-_]+$/', $value);
    }

    protected function validateShortcode($field, $value, $parameters)
    {
        return $value === null || shortcode_exists($value);
    }
}

// Helper function for dot notation array access
if (!function_exists('data_get')) {
    function data_get($target, $key, $default = null)
    {
        if (is_null($key)) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } else {
                return $default;
            }
        }

        return $target;
    }
}