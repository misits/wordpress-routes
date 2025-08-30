<?php
/**
 * WordPress Routes Bootstrap File
 *
 * This file initializes the WordPress Routes library.
 * Include this file in your plugin or theme to use the routing system.
 *
 * @package WordPressRoutes
 * @version 1.0.0
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Prevent multiple loading
if (defined("WPROUTES_LOADED")) {
    return;
}

// Define constants
define("WPROUTES_LOADED", true);
define("WPROUTES_VERSION", "1.0.0");
define("WPROUTES_DIR", __DIR__);
define("WPROUTES_SRC_DIR", __DIR__ . "/src");

// Auto-configure paths based on WPROUTES_MODE (or fallback to WPORM_MODE)
if (
    !defined("WPROUTES_CONTROLLER_PATHS") ||
    !defined("WPROUTES_MIDDLEWARE_PATHS")
) {
    $mode = defined("WPROUTES_MODE")
        ? WPROUTES_MODE
        : (defined("WPORM_MODE")
            ? WPORM_MODE
            : "theme");
    $controllerPaths = [];
    $middlewarePaths = [];

    switch ($mode) {
        case "plugin":
            // Plugin mode: Use plugin directory structure
            // Find the plugin root by looking for the main plugin file in the call stack
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (
                    isset($trace["file"]) &&
                    strpos($trace["file"], "/wp-content/plugins/") !== false
                ) {
                    $pluginDir = plugin_dir_path($trace["file"]);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Go up from lib/wp-routes to assumed plugin root
            }
            $controllerPaths[] = $pluginDir . "src/Controllers";
            $controllerPaths[] = $pluginDir . "controllers";
            $middlewarePaths[] = $pluginDir . "src/Middleware";
            $middlewarePaths[] = $pluginDir . "middleware";
            break;

        case "theme":
        default:
            // Theme mode: Use app directory structure
            if (function_exists("get_template_directory")) {
                $controllerPaths[] =
                    get_template_directory() . "/app/Controllers";
                $controllerPaths[] = get_template_directory() . "/controllers"; // Backward compatibility
                $controllerPaths[] =
                    get_template_directory() . "/api/controllers";
                $middlewarePaths[] =
                    get_template_directory() . "/app/Middleware";
                $middlewarePaths[] = get_template_directory() . "/middleware"; // Backward compatibility
                $middlewarePaths[] =
                    get_template_directory() . "/api/middleware";
            }

            // Add child theme paths if exists
            if (
                function_exists("get_stylesheet_directory") &&
                get_template_directory() !== get_stylesheet_directory()
            ) {
                $controllerPaths[] =
                    get_stylesheet_directory() . "/app/Controllers";
                $controllerPaths[] =
                    get_stylesheet_directory() . "/controllers";
                $controllerPaths[] =
                    get_stylesheet_directory() . "/api/controllers";
                $middlewarePaths[] =
                    get_stylesheet_directory() . "/app/Middleware";
                $middlewarePaths[] = get_stylesheet_directory() . "/middleware";
                $middlewarePaths[] =
                    get_stylesheet_directory() . "/api/middleware";
            }
            break;
    }

    if (!defined("WPROUTES_CONTROLLER_PATHS")) {
        define("WPROUTES_CONTROLLER_PATHS", $controllerPaths);
    }

    if (!defined("WPROUTES_MIDDLEWARE_PATHS")) {
        define("WPROUTES_MIDDLEWARE_PATHS", $middlewarePaths);
    }
}

// Load the autoloader
require_once WPROUTES_SRC_DIR . "/Autoloader.php";

// Register autoloader first
\WordPressRoutes\Routing\Autoloader::register();

// Then load core components explicitly
\WordPressRoutes\Routing\Autoloader::loadCore();

// Register controller autoloader
\WordPressRoutes\Routing\ControllerAutoloader::register();

// Initialize middleware registry
\WordPressRoutes\Routing\MiddlewareRegistry::init();

// Optional: Auto-initialize on WordPress init
// You can disable this by defining WPROUTES_NO_AUTO_INIT before including bootstrap
if (!defined("WPROUTES_NO_AUTO_INIT")) {
    add_action(
        "init",
        function () {
            if (class_exists("\WordPressRoutes\Routing\RouteManager")) {
                \WordPressRoutes\Routing\RouteManager::boot();
            }
        },
        5,
    ); // Priority 5 to run early
}

// Auto-load routes.php based on WPROUTES_MODE
// You can disable this by defining WPROUTES_NO_AUTO_ROUTES before including bootstrap
if (!defined("WPROUTES_NO_AUTO_ROUTES")) {
    // IMPORTANT: Load routes on 'init' for web routes to work properly
    // Web routes need to be registered before WordPress processes the request
    add_action(
        "init",
        function () {
            wproutes_auto_load_routes();
        },
        5, // Early priority to load routes before WordPress processes requests
    );

    // Also load on rest_api_init for API routes
    add_action(
        "rest_api_init",
        function () {
            // Only load if not already loaded
            static $loaded = false;
            if (!$loaded) {
                wproutes_auto_load_routes();
                $loaded = true;
            }
        },
        1,
    );
}

/**
 * Auto-load routes.php file based on WPROUTES_MODE
 */
function wproutes_auto_load_routes()
{
    static $loaded = false;

    // Prevent duplicate loading
    if ($loaded) {
        return;
    }

    // Auto-scaffold routes if they don't exist
    wproutes_scaffold_routes();

    $mode = defined("WPROUTES_MODE")
        ? WPROUTES_MODE
        : (defined("WPORM_MODE")
            ? WPORM_MODE
            : "theme");
    $routes_files = [];

    switch ($mode) {
        case "plugin":
            // Plugin mode: Look for routes.php in plugin directory
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (
                    isset($trace["file"]) &&
                    strpos($trace["file"], "/wp-content/plugins/") !== false
                ) {
                    $pluginDir = plugin_dir_path($trace["file"]);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Go up from lib/wp-routes to assumed plugin root
            }

            $routes_files = [
                $pluginDir . "routes.php",
                $pluginDir . "routes/api.php",
                $pluginDir . "src/routes.php",
            ];
            break;

        case "theme":
        default:
            // Theme mode: Look for organized routes directory structure
            if (function_exists("get_template_directory")) {
                $theme_dir = get_template_directory();

                // Check for routes directory with organized files
                $routes_dir = $theme_dir . "/routes";
                if (is_dir($routes_dir)) {
                    $routes_files = [
                        $routes_dir . "/api.php",
                        $routes_dir . "/auth.php",
                        $routes_dir . "/web.php",
                    ];
                } else {
                    // Fallback to old structure for backward compatibility
                    $routes_files = [
                        $theme_dir . "/routes.php",
                        $theme_dir . "/routes/api.php",
                        $theme_dir . "/api/routes.php",
                    ];
                }

                // Add child theme paths if exists
                if (
                    function_exists("get_stylesheet_directory") &&
                    get_template_directory() !== get_stylesheet_directory()
                ) {
                    $child_dir = get_stylesheet_directory();
                    $child_routes_dir = $child_dir . "/routes";

                    if (is_dir($child_routes_dir)) {
                        // Child theme has organized routes
                        array_unshift(
                            $routes_files,
                            $child_routes_dir . "/api.php",
                            $child_routes_dir . "/auth.php",
                            $child_routes_dir . "/web.php",
                        );
                    } else {
                        // Child theme fallback
                        array_unshift(
                            $routes_files,
                            $child_dir . "/routes.php",
                            $child_dir . "/routes/api.php",
                            $child_dir . "/api/routes.php",
                        );
                    }
                }
            }
            break;
    }

    // Load all found routes files (for organized structure)
    foreach ($routes_files as $routes_file) {
        if (file_exists($routes_file)) {
            // Ensure all core classes are loaded before requiring routes
            if (!class_exists("\WordPressRoutes\Routing\Route")) {
                \WordPressRoutes\Routing\Autoloader::loadCore();
            }
            require_once $routes_file;
            $loaded = true;
        }
    }

    // Log if no routes file was found (only in debug mode)
    if (!$loaded && defined("WP_DEBUG") && WP_DEBUG) {
        $attempted = implode(", ", $routes_files);
        error_log(
            "WordPress Routes: No routes file found. Attempted: {$attempted}",
        );
    }
}

/**
 * Load template with variable replacement (similar to wordpress-skin)
 *
 * @param string $template_name Template name (without .template extension)
 * @param array $replacements Key-value pairs for template variables
 * @return string Template content with variables replaced
 */
function wproutes_load_template($template_name, $replacements = [])
{
    $template_path =
        WPROUTES_DIR . "/templates/" . $template_name . ".template";

    if (!file_exists($template_path)) {
        return "";
    }

    $content = file_get_contents($template_path);

    // Replace placeholders
    foreach ($replacements as $key => $value) {
        $content = str_replace("{{" . $key . "}}", $value, $content);
    }

    return $content;
}

/**
 * Scaffold routes directory with template files
 *
 * @return void
 */
function wproutes_scaffold_routes()
{
    // Determine base directory based on mode
    $mode = defined("WPROUTES_MODE")
        ? WPROUTES_MODE
        : (defined("WPORM_MODE")
            ? WPORM_MODE
            : "theme");

    $base_dir = null;
    $context_name = "";

    switch ($mode) {
        case "plugin":
            // Find plugin root directory
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (
                    isset($trace["file"]) &&
                    strpos($trace["file"], "/wp-content/plugins/") !== false
                ) {
                    $plugin_file = $trace["file"];
                    while (dirname($plugin_file) !== "/wp-content/plugins") {
                        $plugin_file = dirname($plugin_file);
                    }
                    $base_dir = $plugin_file;
                    $context_name = basename($plugin_file);
                    break;
                }
            }
            break;

        case "theme":
        default:
            $base_dir = get_stylesheet_directory();
            $context_name = get_stylesheet();
            break;
    }

    if (!$base_dir || !$context_name) {
        return;
    }

    $routes_dir = $base_dir . "/routes";

    // Create routes directory if it doesn't exist
    if (!is_dir($routes_dir)) {
        wp_mkdir_p($routes_dir);
    }

    // Prepare template variables
    $template_vars = [
        "THEME_NAME" => $context_name,
        "THEME_SLUG" => $context_name,
        "NAMESPACE" => sanitize_title($context_name) . "/v1",
    ];

    // Template files to create
    $template_files = [
        "api.php" => "api.php",
        "web.php" => "web.php",
        "auth.php" => "auth.php",
        "webhooks.php" => "webhooks.php",
    ];

    // Create route files from templates if they don't exist
    foreach ($template_files as $target_file => $template_name) {
        $target_path = $routes_dir . "/" . $target_file;

        // Only create if target doesn't exist
        if (!file_exists($target_path)) {
            $template_content = wproutes_load_template(
                $template_name,
                $template_vars,
            );
            if (!empty($template_content)) {
                file_put_contents($target_path, $template_content);
            }
        }
    }
}

/**
 * Get the WordPress Routes version
 *
 * @return string
 */
function wproutes_version()
{
    return WPROUTES_VERSION;
}

/**
 * Check if WordPress Routes is loaded
 *
 * @return bool
 */
function wproutes_is_loaded()
{
    return defined("WPROUTES_LOADED");
}

/**
 * Boot the routing system manually
 *
 * @return void
 */
function wproutes_boot()
{
    if (class_exists("\WordPressRoutes\Routing\RouteManager")) {
        \WordPressRoutes\Routing\RouteManager::boot();
    }
}

/**
 * Register a GET route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_get($path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::get($path, $callback);
}

/**
 * Register a POST route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_post($path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::post($path, $callback);
}

/**
 * Register a route for any HTTP method
 *
 * @param string $method HTTP method
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_any($method, $path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::match(
        [$method],
        $path,
        $callback,
    );
}

/**
 * Register a PUT route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_put($path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::put($path, $callback);
}

/**
 * Register a DELETE route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_delete($path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::delete($path, $callback);
}

/**
 * Register a PATCH route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\Route
 */
function route_patch($path, $callback)
{
    return \WordPressRoutes\Routing\RouteManager::patch($path, $callback);
}

/**
 * Register route group
 *
 * @param array $attributes Group attributes (namespace, middleware, etc.)
 * @param callable $callback Group definition callback
 */
function route_group(array $attributes, callable $callback)
{
    \WordPressRoutes\Routing\RouteManager::group($attributes, $callback);
}

/**
 * Register resource routes (CRUD)
 *
 * @param string $name Resource name
 * @param string $controller Controller class
 * @param array $options Options (only, except, etc.)
 * @return array Array of created routes
 */
function route_resource($name, $controller, array $options = [])
{
    return \WordPressRoutes\Routing\RouteManager::resource(
        $name,
        $controller,
        $options,
    );
}

/**
 * Generate URL for named route
 *
 * @param string $name Route name
 * @param array $params Parameters
 * @return string|null
 */
function route_url($name, array $params = [])
{
    return \WordPressRoutes\Routing\RouteManager::url($name, $params);
}

/**
 * Create a new controller instance with dependency injection
 *
 * @param string $controller Controller class name
 * @param RouteRequest $request Current request
 * @return object Controller instance
 */
function make_controller($controller, $request = null)
{
    if (!class_exists($controller)) {
        throw new \Exception("Controller class {$controller} not found");
    }

    $instance = new $controller();

    if ($request && method_exists($instance, "setRequest")) {
        $instance->setRequest($request);
    }

    return $instance;
}

/**
 * Add controller search path
 *
 * @param string $path Directory path to search for controllers
 * @param string $namespace Optional namespace prefix
 */
function wroutes_add_controller_path($path, $namespace = "")
{
    \WordPressRoutes\Routing\ControllerAutoloader::addPath($path, $namespace);
}

/**
 * Get default path for specific directory type based on WPROUTES_MODE
 *
 * @param string $type Directory type: 'controllers', 'middleware'
 * @return string Default path for the specified type
 */
function wproutes_get_default_path($type = "controllers")
{
    $mode = defined("WPROUTES_MODE")
        ? WPROUTES_MODE
        : (defined("WPORM_MODE")
            ? WPORM_MODE
            : "theme");

    switch ($mode) {
        case "plugin":
            // Plugin mode: Use plugin directory structure
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (
                    isset($trace["file"]) &&
                    strpos($trace["file"], "/wp-content/plugins/") !== false
                ) {
                    $pluginDir = plugin_dir_path($trace["file"]);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Assume we're in a plugin
            }

            switch ($type) {
                case "controllers":
                    return $pluginDir . "src/Controllers";
                case "middleware":
                    return $pluginDir . "src/Middleware";
            }
            break;

        case "theme":
        default:
            // Theme mode: Use theme directory structure
            $themeDir = function_exists("get_template_directory")
                ? get_template_directory()
                : "";

            switch ($type) {
                case "controllers":
                    return $themeDir . "/app/Controllers";
                case "middleware":
                    return $themeDir . "/app/Middleware";
            }
            break;
    }

    return "";
}

/**
 * Helper function to load a controller class (works in theme and plugin mode)
 *
 * @param string $controllerName The controller class name (e.g., 'ProductController', 'UserController')
 * @return bool True if loaded successfully
 */
function wproutes_load_controller($controllerName)
{
    // Check if class already exists
    if (class_exists($controllerName)) {
        return true;
    }

    // Get controller paths based on WPROUTES_MODE
    $controllerPaths = wproutes_get_controller_paths();

    // Try to load from each path
    foreach ($controllerPaths as $path) {
        $controllerFile = $path . "/" . $controllerName . ".php";

        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            if (class_exists($controllerName)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Get controller search paths based on WPROUTES_MODE
 *
 * @return array Array of directory paths to search for controllers
 */
function wproutes_get_controller_paths()
{
    $mode = defined("WPROUTES_MODE")
        ? WPROUTES_MODE
        : (defined("WPORM_MODE")
            ? WPORM_MODE
            : "theme");
    $paths = [];

    switch ($mode) {
        case "plugin":
            // Plugin mode: Use plugin directory structure
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (
                    isset($trace["file"]) &&
                    strpos($trace["file"], "/wp-content/plugins/") !== false
                ) {
                    $pluginDir = plugin_dir_path($trace["file"]);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Assume we're in a plugin
            }
            $paths[] = $pluginDir . "src/Controllers";
            $paths[] = $pluginDir . "controllers";
            break;

        case "theme":
        default:
            // Theme mode: Use app directory structure (with backward compatibility)
            if (function_exists("get_template_directory")) {
                $paths[] = get_template_directory() . "/app/Controllers";
                $paths[] = get_template_directory() . "/controllers"; // Backward compatibility
                $paths[] = get_template_directory() . "/api/controllers";
            }

            // Add child theme path if exists
            if (
                function_exists("get_stylesheet_directory") &&
                get_template_directory() !== get_stylesheet_directory()
            ) {
                $paths[] = get_stylesheet_directory() . "/app/Controllers";
                $paths[] = get_stylesheet_directory() . "/controllers";
                $paths[] = get_stylesheet_directory() . "/api/controllers";
            }
            break;
    }

    return $paths;
}

/**
 * Helper function to use a controller with automatic loading
 *
 * @param string $controllerName The controller class name
 * @return string The controller class name if loaded
 * @throws Exception If controller not found
 */
function wproutes_controller($controllerName)
{
    if (wproutes_load_controller($controllerName)) {
        return $controllerName;
    }

    throw new Exception(
        "Controller '{$controllerName}' not found. Make sure the controller file exists in the controllers directory.",
    );
}

// Backward compatibility aliases
if (!function_exists("load_controller")) {
    function load_controller($controllerName)
    {
        return wproutes_load_controller($controllerName);
    }
}

if (!function_exists("controller")) {
    function controller($controllerName)
    {
        return wproutes_controller($controllerName);
    }
}

/**
 * Normalize template name - auto-append .php extension if missing
 *
 * @param string $template Template name
 * @return string Normalized template name with .php extension
 */
function wproutes_normalize_template_name($template)
{
    // If template already has .php extension, return as-is
    if (str_ends_with(strtolower($template), ".php")) {
        return $template;
    }

    // Auto-append .php extension
    return $template . ".php";
}

/**
 * Find view template with same logic as route template resolution
 * Searches in resources/views first, then fallback to theme root
 *
 * @param string $template Template name (with or without .php extension)
 * @return string|null Full path to template file, or null if not found
 */
function wproutes_find_view_template($template)
{
    // Normalize template name (auto-append .php if missing)
    $normalizedTemplate = wproutes_normalize_template_name($template);

    // If it's an absolute path, use it directly
    if (
        str_starts_with($normalizedTemplate, "/") &&
        file_exists($normalizedTemplate)
    ) {
        return $normalizedTemplate;
    }

    // Search locations in priority order
    $searchPaths = [];

    // Child theme paths (if different from parent)
    if (get_template_directory() !== get_stylesheet_directory()) {
        $childDir = get_stylesheet_directory();

        if (str_contains($normalizedTemplate, "/")) {
            // Template has path - search directly and in resources/views
            $searchPaths[] = $childDir . "/" . $normalizedTemplate;
            $searchPaths[] =
                $childDir . "/resources/views/" . $normalizedTemplate;
        } else {
            // Simple filename - prioritize resources/views
            $searchPaths[] =
                $childDir . "/resources/views/" . $normalizedTemplate;
            $searchPaths[] = $childDir . "/" . $normalizedTemplate;
        }
    }

    // Parent theme paths
    $parentDir = get_template_directory();

    if (str_contains($normalizedTemplate, "/")) {
        // Template has path - search directly and in resources/views
        $searchPaths[] = $parentDir . "/" . $normalizedTemplate;
        $searchPaths[] = $parentDir . "/resources/views/" . $normalizedTemplate;
    } else {
        // Simple filename - prioritize resources/views
        $searchPaths[] = $parentDir . "/resources/views/" . $normalizedTemplate;
        $searchPaths[] = $parentDir . "/" . $normalizedTemplate;
    }

    // Try WordPress's locate_template as fallback
    $wpTemplate = locate_template($normalizedTemplate);
    if ($wpTemplate) {
        $searchPaths[] = $wpTemplate;
    }

    // Return first existing template
    foreach ($searchPaths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Laravel-style view helper for WordPress routes
 *
 * @param string $template Template name (with or without .php extension)
 * @param array $data Data to pass to the template
 * @return string Rendered template content
 */
function view($template, $data = [])
{
    // Extract variables to make them available in the template
    extract($data);

    // Start output buffering
    ob_start();

    // Include WordPress header
    get_header();

    // Find the template using the same logic as routes
    $template_path = wproutes_find_view_template($template);

    if ($template_path && file_exists($template_path)) {
        include $template_path;
    } else {
        echo '<div class="error">Template not found: ' .
            esc_html($template) .
            "</div>";
    }

    // Include WordPress footer
    get_footer();

    // Get the buffered content and clean the buffer
    $content = ob_get_clean();

    // Return the content
    return $content;
}

/**
 * Backward compatibility alias for view()
 *
 * @param string $template Template name (with or without .php extension)
 * @param array $data Data to pass to the template
 * @return string Rendered template content
 */
function wproutes_view($template, $data = [])
{
    return view($template, $data);
}

/**
 * Helper function to generate API route URLs
 *
 * @param string $endpoint The API endpoint (e.g., 'auth/login')
 * @param string|null $namespace Optional namespace override (defaults to current namespace)
 * @return string The full API URL
 */
function wproutes_api_url($endpoint, $namespace = null)
{
    // Get the current namespace or use provided one
    if ($namespace === null) {
        $namespace = \WordPressRoutes\Routing\RouteManager::getNamespace();
    }

    // Build the full path
    $path = trim($namespace, "/") . "/" . ltrim($endpoint, "/");

    // Use rest_url which respects the rest_url_prefix filter
    return rest_url($path);
}

/**
 * Helper function to get just the API base URL
 *
 * @return string The API base URL
 */
function wproutes_api_base()
{
    return rest_url();
}

/**
 * Helper function to get the REST API prefix (e.g., 'api' or 'wp-json')
 *
 * @return string The REST API prefix
 */
function wproutes_api_prefix()
{
    return rest_get_url_prefix();
}

/**
 * Helper function to generate URL from route name
 *
 * @param string $name The route name
 * @param array $params Optional parameters for the route
 * @return string|null The full URL or null if route not found
 */
function route($name, array $params = [])
{
    return \WordPressRoutes\Routing\RouteManager::url($name, $params);
}

// Register WP-CLI commands after WordPress is initialized
if (defined("WP_CLI") && WP_CLI) {
    add_action(
        "init",
        function () {
            require_once __DIR__ . "/cli/WP/CommandRegistrar.php";
            \WordPressRoutes\CLI\WP\CommandRegistrar::register();
        },
        10,
    );
}
