<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Base Controller Class
 *
 * Provides common functionality for all API controllers
 * Includes validation, response formatting, authentication helpers, etc.
 *
 * @since 1.0.0
 */
abstract class BaseController
{
    /**
     * Current request instance
     *
     * @var RouteRequest
     */
    protected $request;

    /**
     * Default validation rules for this controller
     *
     * @var array
     */
    protected $validationRules = [];

    /**
     * Default middleware for this controller
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Items per page for pagination
     *
     * @var int
     */
    protected $perPage = 15;

    /**
     * Maximum items per page
     *
     * @var int
     */
    protected $maxPerPage = 100;

    /**
     * Set the current request
     *
     * @param RouteRequest $request
     * @return $this
     */
    public function setRequest(RouteRequest $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get the current request
     *
     * @return RouteRequest
     */
    protected function request()
    {
        return $this->request;
    }

    /**
     * Validate request data
     *
     * @param array $rules Custom validation rules
     * @return array|\WP_Error
     */
    protected function validate(array $rules = [])
    {
        $rules = array_merge($this->validationRules, $rules);
        return $this->request->validate($rules);
    }

    /**
     * Return success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return \WP_REST_Response
     */
    protected function success(
        $data = null,
        $message = "Success",
        $status = 200,
    ) {
        $response = [
            "success" => true,
            "message" => $message,
        ];

        if ($data !== null) {
            $response["data"] = $data;
        }

        return new \WP_REST_Response($response, $status);
    }

    /**
     * Return error response as WP_REST_Response
     *
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array $data Additional error data
     * @return \WP_REST_Response
     */
    protected function errorResponse($message, $status = 400, $data = [])
    {
        $response = [
            "success" => false,
            "message" => $message,
        ];

        if (!empty($data)) {
            $response["data"] = $data;
        }

        return new \WP_REST_Response($response, $status);
    }

    /**
     * Return error response
     *
     * @param string $message Error message
     * @param string $code Error code
     * @param int $status HTTP status code
     * @param mixed $details Additional error details
     * @return \WP_Error
     */
    protected function error(
        $message,
        $code = "error",
        $status = 400,
        $details = null,
    ) {
        $data = ["status" => $status];

        if ($details !== null) {
            $data["details"] = $details;
        }

        return new \WP_Error($code, $message, $data);
    }

    /**
     * Return validation error response
     *
     * @param array $errors Validation errors
     * @return \WP_Error
     */
    protected function validationError(array $errors)
    {
        return $this->error("Validation failed", "validation_failed", 422, [
            "errors" => $errors,
        ]);
    }

    /**
     * Return not found error
     *
     * @param string $resource Resource name
     * @return \WP_Error
     */
    protected function notFound($resource = "Resource")
    {
        return $this->error(
            $resource . " not found",
            strtolower($resource) . "_not_found",
            404,
        );
    }

    /**
     * Return forbidden error
     *
     * @param string $message Custom message
     * @return \WP_Error
     */
    protected function forbidden($message = "Access denied")
    {
        return $this->error($message, "forbidden", 403);
    }

    /**
     * Return unauthorized error
     *
     * @return \WP_Error
     */
    protected function unauthorized()
    {
        return $this->error("Authentication required", "unauthorized", 401);
    }

    /**
     * Check if current user can perform action
     *
     * @param string $capability WordPress capability
     * @param mixed $args Additional arguments for capability check
     * @return bool|\WP_Error Returns true if allowed, WP_Error if not
     */
    protected function authorize($capability, ...$args)
    {
        if (!is_user_logged_in()) {
            return $this->unauthorized();
        }

        if (!current_user_can($capability, ...$args)) {
            return $this->forbidden();
        }

        return true;
    }

    /**
     * Get current user
     *
     * @return \WP_User|false
     */
    protected function user()
    {
        return $this->request ? $this->request->user() : wp_get_current_user();
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    protected function userId()
    {
        return $this->request
            ? $this->request->userId()
            : get_current_user_id();
    }

    /**
     * Get pagination parameters
     *
     * @return array
     */
    protected function getPagination()
    {
        $page = max(1, (int) $this->request->query("page", 1));
        $perPage = min(
            $this->maxPerPage,
            max(1, (int) $this->request->query("per_page", $this->perPage)),
        );

        return [
            "page" => $page,
            "per_page" => $perPage,
            "offset" => ($page - 1) * $perPage,
        ];
    }

    /**
     * Format paginated response
     *
     * @param array $items Items for current page
     * @param int $total Total items count
     * @param array $pagination Pagination parameters
     * @return array
     */
    protected function paginatedResponse(
        array $items,
        $total,
        array $pagination,
    ) {
        $totalPages = ceil($total / $pagination["per_page"]);

        return [
            "data" => $items,
            "pagination" => [
                "current_page" => $pagination["page"],
                "per_page" => $pagination["per_page"],
                "total_items" => $total,
                "total_pages" => $totalPages,
                "has_next" => $pagination["page"] < $totalPages,
                "has_prev" => $pagination["page"] > 1,
            ],
        ];
    }

    /**
     * Get query parameters for search/filtering
     *
     * @param array $defaults Default parameters
     * @return array
     */
    protected function getQueryParams(array $defaults = [])
    {
        $params = [];

        // Search
        if ($this->request->query("search")) {
            $params["search"] = sanitize_text_field(
                $this->request->query("search"),
            );
        }

        // Ordering
        if ($this->request->query("orderby")) {
            $params["orderby"] = sanitize_text_field(
                $this->request->query("orderby"),
            );
            $params["order"] =
                strtoupper($this->request->query("order", "ASC")) === "DESC"
                    ? "DESC"
                    : "ASC";
        }

        // Status filter
        if ($this->request->query("status")) {
            $params["status"] = sanitize_text_field(
                $this->request->query("status"),
            );
        }

        // Date filters
        if ($this->request->query("date_after")) {
            $params["date_after"] = sanitize_text_field(
                $this->request->query("date_after"),
            );
        }
        if ($this->request->query("date_before")) {
            $params["date_before"] = sanitize_text_field(
                $this->request->query("date_before"),
            );
        }

        return array_merge($defaults, $params);
    }

    /**
     * Sanitize input data
     *
     * @param array $data Input data
     * @param array $rules Sanitization rules
     * @return array
     */
    protected function sanitize(array $data, array $rules = [])
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $rule = $rules[$key] ?? "text";

            switch ($rule) {
                case "email":
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case "url":
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case "html":
                    $sanitized[$key] = wp_kses_post($value);
                    break;
                case "textarea":
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case "int":
                    $sanitized[$key] = (int) $value;
                    break;
                case "float":
                    $sanitized[$key] = (float) $value;
                    break;
                case "bool":
                    $sanitized[$key] = (bool) $value;
                    break;
                case "text":
                default:
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Log activity for debugging/auditing
     *
     * @param string $action Action performed
     * @param array $data Additional data
     */
    protected function log($action, array $data = [])
    {
        if (defined("WP_DEBUG") && WP_DEBUG) {
            $logData = array_merge(
                [
                    "timestamp" => current_time("mysql"),
                    "user_id" => $this->userId(),
                    "ip" => $this->request
                        ? $this->request->ip()
                        : $_SERVER["REMOTE_ADDR"],
                    "action" => $action,
                    "controller" => get_class($this),
                ],
                $data,
            );

            error_log("[WPRoutes] " . json_encode($logData));
        }
    }

    /**
     * Check rate limiting for current user/IP
     *
     * @param int $limit Requests per window
     * @param int $window Time window in seconds
     * @return bool|\WP_Error
     */
    protected function checkRateLimit($limit = 60, $window = 60)
    {
        $identifier = $this->request->isAuthenticated()
            ? "user_" . $this->userId()
            : "ip_" . $this->request->ip();

        $key = "rate_limit_" . md5(get_class($this) . "_" . $identifier);
        $requests = get_transient($key) ?: [];
        $now = time();

        // Clean old requests
        $requests = array_filter($requests, function ($timestamp) use (
            $now,
            $window,
        ) {
            return $now - $timestamp < $window;
        });

        if (count($requests) >= $limit) {
            return $this->error(
                "Rate limit exceeded. Please try again later.",
                "rate_limit_exceeded",
                429,
            );
        }

        // Add current request
        $requests[] = $now;
        set_transient($key, $requests, $window);

        return true;
    }

    /**
     * Transform data for API response
     * Override this method in child controllers for custom transformations
     *
     * @param mixed $data Raw data
     * @return mixed Transformed data
     */
    protected function transform($data)
    {
        return $data;
    }

    /**
     * Transform collection of data
     *
     * @param array $collection
     * @return array
     */
    protected function transformCollection(array $collection)
    {
        return array_map([$this, "transform"], $collection);
    }

    /**
     * Handle file upload
     *
     * @param string $field File field name
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Max file size in bytes
     * @return array|\WP_Error Upload result or error
     */
    protected function handleUpload(
        $field,
        array $allowedTypes = [],
        $maxSize = null,
    ) {
        if (!$this->request->hasFile($field)) {
            return $this->error("No file uploaded", "no_file", 400);
        }

        $file = $this->request->file($field);

        // Check file size
        if ($maxSize && $file["size"] > $maxSize) {
            return $this->error("File too large", "file_too_large", 400);
        }

        // Check file type
        if (!empty($allowedTypes) && !in_array($file["type"], $allowedTypes)) {
            return $this->error(
                "File type not allowed",
                "invalid_file_type",
                400,
            );
        }

        // Handle upload
        require_once ABSPATH . "wp-admin/includes/file.php";

        $upload = wp_handle_upload($file, ["test_form" => false]);

        if (isset($upload["error"])) {
            return $this->error($upload["error"], "upload_error", 400);
        }

        return $upload;
    }
}
