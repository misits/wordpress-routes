<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * WordPress Routes Autoloader
 *
 * PSR-4 compatible autoloader for WordPress Routes library
 * Similar structure to WordPress ORM but for routing functionality
 *
 * @since 1.0.0
 */
class Autoloader
{
    /**
     * Namespace prefix
     */
    public const string NAMESPACE_PREFIX = "WordPressRoutes\\";

    /**
     * Base directory for the namespace prefix
     */
    protected static $baseDir;

    /**
     * Register the autoloader
     */
    public static function register()
    {
        self::$baseDir = dirname(__FILE__) . "/";
        spl_autoload_register([__CLASS__, "loadClass"]);
    }

    /**
     * Load a class file
     *
     * @param string $class The fully-qualified class name
     * @return mixed The mapped file name on success, or boolean false on failure
     */
    public static function loadClass($class)
    {
        // Does the class use the namespace prefix?
        $len = strlen(self::NAMESPACE_PREFIX);
        if (strncmp(self::NAMESPACE_PREFIX, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file =
            self::$baseDir . str_replace("\\", "/", $relativeClass) . ".php";

        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Load core components
     */
    public static function loadCore()
    {
        $coreClasses = [
            "Route",
            "RouteManager",
            "RouteRequest",
            "BaseController",
            "ControllerAutoloader",
            "MiddlewareRegistry",
            "Middleware/MiddlewareInterface",
            "Middleware/AuthMiddleware",
            "Middleware/CapabilityMiddleware",
            "Middleware/CorsMiddleware",
            "Middleware/ValidationMiddleware",
            "Middleware/JsonOnlyMiddleware",
            "Middleware/NonceMiddleware",
            "Middleware/RateLimitMiddleware",
            "Validation/Validator",
            "Validation/FormRequest",
        ];

        foreach ($coreClasses as $class) {
            $file = self::$baseDir . $class . ".php";
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }
}
