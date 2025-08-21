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
if (!defined("WPROUTES_CONTROLLER_PATHS") || !defined("WPROUTES_MIDDLEWARE_PATHS")) {
    $mode = defined("WPROUTES_MODE") ? WPROUTES_MODE : (defined("WPORM_MODE") ? WPORM_MODE : "theme");
    $controllerPaths = [];
    $middlewarePaths = [];
    
    switch ($mode) {
        case "plugin":
            // Plugin mode: Use plugin directory structure
            // Find the plugin root by looking for the main plugin file in the call stack
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (isset($trace['file']) && strpos($trace['file'], '/wp-content/plugins/') !== false) {
                    $pluginDir = plugin_dir_path($trace['file']);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Go up from lib/wp-routes to assumed plugin root
            }
            $controllerPaths[] = $pluginDir . 'src/Controllers';
            $controllerPaths[] = $pluginDir . 'controllers';
            $middlewarePaths[] = $pluginDir . 'src/Middleware';
            $middlewarePaths[] = $pluginDir . 'middleware';
            break;
            
        case "theme":
        default:
            // Theme mode: Use theme directory structure
            if (function_exists('get_template_directory')) {
                $controllerPaths[] = get_template_directory() . '/controllers';
                $controllerPaths[] = get_template_directory() . '/api/controllers';
                $middlewarePaths[] = get_template_directory() . '/middleware';
                $middlewarePaths[] = get_template_directory() . '/api/middleware';
            }
            
            // Add child theme paths if exists
            if (function_exists('get_stylesheet_directory') && get_template_directory() !== get_stylesheet_directory()) {
                $controllerPaths[] = get_stylesheet_directory() . '/controllers';
                $controllerPaths[] = get_stylesheet_directory() . '/api/controllers';
                $middlewarePaths[] = get_stylesheet_directory() . '/middleware';
                $middlewarePaths[] = get_stylesheet_directory() . '/api/middleware';
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

// Register autoloader
\WordPressRoutes\Routing\Autoloader::register();

// Load core components
\WordPressRoutes\Routing\Autoloader::loadCore();

// Register controller autoloader
\WordPressRoutes\Routing\ControllerAutoloader::register();

// Optional: Auto-initialize on WordPress init
// You can disable this by defining WPROUTES_NO_AUTO_INIT before including bootstrap
if (!defined("WPROUTES_NO_AUTO_INIT")) {
    add_action(
        "init",
        function () {
            if (class_exists("\WordPressRoutes\Routing\ApiManager")) {
                \WordPressRoutes\Routing\ApiManager::boot();
            }
        },
        5,
    ); // Priority 5 to run early
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
    if (class_exists("\WordPressRoutes\Routing\ApiManager")) {
        \WordPressRoutes\Routing\ApiManager::boot();
    }
}

/**
 * Register a GET route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_get($path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::get($path, $callback);
}

/**
 * Register a POST route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_post($path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::post($path, $callback);
}

/**
 * Register a route for any HTTP method
 *
 * @param string $method HTTP method
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_any($method, $path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::match([$method], $path, $callback);
}

/**
 * Register a PUT route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_put($path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::put($path, $callback);
}

/**
 * Register a DELETE route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_delete($path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::delete($path, $callback);
}

/**
 * Register a PATCH route
 *
 * @param string $path Route path
 * @param callable|string $callback Route handler
 * @return \WordPressRoutes\Routing\ApiRoute
 */
function route_patch($path, $callback)
{
    return \WordPressRoutes\Routing\ApiManager::patch($path, $callback);
}

/**
 * Register route group
 *
 * @param array $attributes Group attributes (namespace, middleware, etc.)
 * @param callable $callback Group definition callback
 */
function route_group(array $attributes, callable $callback)
{
    \WordPressRoutes\Routing\ApiManager::group($attributes, $callback);
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
    return \WordPressRoutes\Routing\ApiManager::resource($name, $controller, $options);
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
    return \WordPressRoutes\Routing\ApiManager::url($name, $params);
}

/**
 * Create a new controller instance with dependency injection
 *
 * @param string $controller Controller class name
 * @param ApiRequest $request Current request
 * @return object Controller instance
 */
function make_controller($controller, $request = null)
{
    if (!class_exists($controller)) {
        throw new \Exception("Controller class {$controller} not found");
    }
    
    $instance = new $controller();
    
    if ($request && method_exists($instance, 'setRequest')) {
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
function wroutes_add_controller_path($path, $namespace = '')
{
    \WordPressRoutes\Routing\ControllerAutoloader::addPath($path, $namespace);
}

/**
 * Get default path for specific directory type based on WPROUTES_MODE
 * 
 * @param string $type Directory type: 'controllers', 'middleware'
 * @return string Default path for the specified type
 */
function wproutes_get_default_path($type = 'controllers')
{
    $mode = defined('WPROUTES_MODE') ? WPROUTES_MODE : (defined('WPORM_MODE') ? WPORM_MODE : 'theme');
    
    switch ($mode) {
        case 'plugin':
            // Plugin mode: Use plugin directory structure
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (isset($trace['file']) && strpos($trace['file'], '/wp-content/plugins/') !== false) {
                    $pluginDir = plugin_dir_path($trace['file']);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Assume we're in a plugin
            }
            
            switch ($type) {
                case 'controllers':
                    return $pluginDir . 'src/Controllers';
                case 'middleware':
                    return $pluginDir . 'src/Middleware';
            }
            break;
            
        case 'theme':
        default:
            // Theme mode: Use theme directory structure
            $themeDir = function_exists('get_template_directory') ? get_template_directory() : '';
            
            switch ($type) {
                case 'controllers':
                    return $themeDir . '/controllers';
                case 'middleware':
                    return $themeDir . '/middleware';
            }
            break;
    }
    
    return '';
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
        $controllerFile = $path . '/' . $controllerName . '.php';
        
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
    $mode = defined('WPROUTES_MODE') ? WPROUTES_MODE : (defined('WPORM_MODE') ? WPORM_MODE : 'theme');
    $paths = [];
    
    switch ($mode) {
        case 'plugin':
            // Plugin mode: Use plugin directory structure
            $pluginDir = null;
            $backtrace = debug_backtrace();
            foreach ($backtrace as $trace) {
                if (isset($trace['file']) && strpos($trace['file'], '/wp-content/plugins/') !== false) {
                    $pluginDir = plugin_dir_path($trace['file']);
                    break;
                }
            }
            // Fallback if not found in plugins directory
            if (!$pluginDir) {
                $pluginDir = dirname(__DIR__, 2); // Assume we're in a plugin
            }
            $paths[] = $pluginDir . 'src/Controllers';
            $paths[] = $pluginDir . 'controllers';
            break;
            
        case 'theme':
        default:
            // Theme mode: Use theme directory structure
            if (function_exists('get_template_directory')) {
                $paths[] = get_template_directory() . '/controllers';
                $paths[] = get_template_directory() . '/api/controllers';
            }
            
            // Add child theme path if exists
            if (function_exists('get_stylesheet_directory') && get_template_directory() !== get_stylesheet_directory()) {
                $paths[] = get_stylesheet_directory() . '/controllers';
                $paths[] = get_stylesheet_directory() . '/api/controllers';
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
    
    throw new Exception("Controller '{$controllerName}' not found. Make sure the controller file exists in the controllers directory.");
}

// Backward compatibility aliases
if (!function_exists('load_controller')) {
    function load_controller($controllerName) {
        return wproutes_load_controller($controllerName);
    }
}

if (!function_exists('controller')) {
    function controller($controllerName) {
        return wproutes_controller($controllerName);
    }
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
