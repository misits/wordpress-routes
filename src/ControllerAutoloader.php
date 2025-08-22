<?php

namespace WordPressRoutes\Routing;

defined("ABSPATH") or exit();

/**
 * Controller Autoloader
 *
 * Automatically loads controllers from theme/plugin directories
 * Supports nested controller directories and namespaces
 *
 * @since 1.0.0
 */
class ControllerAutoloader
{
    /**
     * Registered controller paths
     *
     * @var array
     */
    protected static $paths = [];

    /**
     * Controller namespace mapping
     *
     * @var array
     */
    protected static $namespaces = [];

    /**
     * Register the controller autoloader
     */
    public static function register()
    {
        // Add default paths
        static::addDefaultPaths();
        
        // Register SPL autoloader
        spl_autoload_register([__CLASS__, 'loadController']);
    }

    /**
     * Add default controller paths
     */
    protected static function addDefaultPaths()
    {
        // Add paths from constant if defined
        if (defined('WPROUTES_CONTROLLER_PATHS')) {
            foreach (WPROUTES_CONTROLLER_PATHS as $path) {
                static::addPath($path);
            }
        }
    }

    /**
     * Add controller search path
     *
     * @param string $path Directory path
     * @param string $namespace Optional namespace prefix
     */
    public static function addPath($path, $namespace = '')
    {
        $path = rtrim($path, '/\\');
        
        if (!in_array($path, static::$paths)) {
            static::$paths[] = $path;
        }
        
        if ($namespace) {
            static::$namespaces[$path] = rtrim($namespace, '\\');
        }
    }


    /**
     * Load controller class
     *
     * @param string $class Class name
     * @return bool
     */
    public static function loadController($class)
    {
        // Only handle classes that look like controllers
        if (!static::isControllerClass($class)) {
            return false;
        }

        // Try to find and load the controller
        return static::findAndLoadController($class);
    }

    /**
     * Check if class name looks like a controller
     *
     * @param string $class
     * @return bool
     */
    protected static function isControllerClass($class)
    {
        // Handle namespaced controllers
        $className = static::getClassName($class);
        
        return (
            strpos($className, 'Controller') !== false ||
            strpos($class, '\\Controllers\\') !== false ||
            strpos($class, '/Controllers/') !== false
        );
    }

    /**
     * Get class name from full class path
     *
     * @param string $class
     * @return string
     */
    protected static function getClassName($class)
    {
        $parts = explode('\\', $class);
        return end($parts);
    }

    /**
     * Find and load controller file
     *
     * @param string $class
     * @return bool
     */
    protected static function findAndLoadController($class)
    {
        $className = static::getClassName($class);
        $relativePath = static::getRelativePath($class);
        
        foreach (static::$paths as $basePath) {
            $possibleFiles = [
                // Direct class name
                $basePath . '/' . $className . '.php',
                // With relative path from namespace
                $basePath . '/' . $relativePath . '.php',
                // Lowercase directory structure
                $basePath . '/' . static::toLowerPath($relativePath) . '.php',
                // PSR-4 style
                $basePath . '/' . str_replace('\\', '/', $relativePath) . '.php',
            ];

            foreach ($possibleFiles as $file) {
                if (file_exists($file)) {
                    require_once $file;
                    return class_exists($class, false);
                }
            }
        }

        return false;
    }

    /**
     * Get relative path for class
     *
     * @param string $class
     * @return string
     */
    protected static function getRelativePath($class)
    {
        // Remove namespace prefixes
        foreach (static::$namespaces as $namespace) {
            if (strpos($class, $namespace . '\\') === 0) {
                $class = substr($class, strlen($namespace) + 1);
                break;
            }
        }

        // Convert namespace to path
        return str_replace('\\', '/', $class);
    }

    /**
     * Convert path to lowercase with proper separators
     *
     * @param string $path
     * @return string
     */
    protected static function toLowerPath($path)
    {
        $parts = explode('/', $path);
        $lastPart = array_pop($parts); // Keep controller name as-is
        
        return implode('/', array_map('strtolower', $parts)) . '/' . $lastPart;
    }

    /**
     * Get all registered paths
     *
     * @return array
     */
    public static function getPaths()
    {
        return static::$paths;
    }

    /**
     * Clear all registered paths (for testing)
     */
    public static function clearPaths()
    {
        static::$paths = [];
        static::$namespaces = [];
    }

    /**
     * Add controller path for specific plugin
     *
     * @param string $pluginFile Plugin main file path
     * @param string $namespace Optional namespace
     */
    public static function addPluginPath($pluginFile, $namespace = '')
    {
        $pluginDir = dirname($pluginFile);
        $controllerPath = $pluginDir . '/controllers';
        
        static::addPath($controllerPath, $namespace);
    }

    /**
     * Discover and register all controller files in paths
     *
     * @param bool $autoload Whether to autoload discovered controllers
     * @return array Discovered controller files
     */
    public static function discoverControllers($autoload = false)
    {
        $controllers = [];

        foreach (static::$paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = static::scanDirectory($path);
            
            foreach ($files as $file) {
                if (static::isPhpFile($file) && static::looksLikeController($file)) {
                    $controllers[] = $file;
                    
                    if ($autoload) {
                        require_once $file;
                    }
                }
            }
        }

        return $controllers;
    }

    /**
     * Recursively scan directory for PHP files
     *
     * @param string $directory
     * @return array
     */
    protected static function scanDirectory($directory)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Check if file is PHP file
     *
     * @param string $file
     * @return bool
     */
    protected static function isPhpFile($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION) === 'php';
    }

    /**
     * Check if file looks like a controller
     *
     * @param string $file
     * @return bool
     */
    protected static function looksLikeController($file)
    {
        $filename = basename($file, '.php');
        return strpos($filename, 'Controller') !== false;
    }

    /**
     * Resolve controller class name to instance
     *
     * @param string $controller Controller class name or short name
     * @return object|null Controller instance or null if not found
     */
    public static function resolve($controller)
    {
        // Handle fully qualified class name
        if (class_exists($controller)) {
            return new $controller();
        }

        // Try with common namespace prefixes
        $possibleClasses = [
            $controller,
            'App\\Controllers\\' . $controller,
            'Controllers\\' . $controller,
            'App\\Http\\Controllers\\' . $controller,
        ];

        // Add Controller suffix if not present
        if (strpos($controller, 'Controller') === false) {
            $possibleClasses[] = $controller . 'Controller';
            $possibleClasses[] = 'App\\Controllers\\' . $controller . 'Controller';
            $possibleClasses[] = 'Controllers\\' . $controller . 'Controller';
            $possibleClasses[] = 'App\\Http\\Controllers\\' . $controller . 'Controller';
        }

        // Try to find and instantiate the controller
        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                return new $className();
            }
        }

        // Try to auto-discover the controller
        $controllers = static::discoverControllers(true);
        
        // Try again after discovery
        foreach ($possibleClasses as $className) {
            if (class_exists($className)) {
                return new $className();
            }
        }

        return null;
    }
}