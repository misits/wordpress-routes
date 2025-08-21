<?php

namespace WordPressRoutes\CLI\WP;

use \WP_CLI_Command;
use \WP_CLI;
use WordPressRoutes\Routing\ControllerAutoloader;

/**
 * WordPress Routes Controller commands for WP-CLI
 */
class ControllerCommand extends \WP_CLI_Command
{
    /**
     * Generate a new controller class
     *
     * ## OPTIONS
     *
     * <name>
     * : Name of the controller
     *
     * [--path=<path>]
     * : Path to controllers directory (optional - will auto-detect based on mode)
     *
     * [--api]
     * : Create an API controller (extends BaseController)
     *
     * [--resource]
     * : Create a resource controller with CRUD methods
     *
     * [--namespace=<namespace>]
     * : Specify namespace for the controller (optional)
     *
     * ## EXAMPLES
     *
     *     wp wproutes make:controller ProductController
     *     wp wproutes make:controller ProductController --api
     *     wp wproutes make:controller ProductController --resource
     *     wp wproutes make:controller Admin/UserController --namespace="MyApp\\Controllers"
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error("Controller name is required.");
        }

        $name = $args[0];
        
        // Ensure name ends with 'Controller'
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        
        // Default path: use mode-based controllers directory (create if doesn't exist)
        $defaultPath = wproutes_get_default_path('controllers');
        
        $path = $assoc_args["path"] ?? $defaultPath;
        $isApi = isset($assoc_args["api"]);
        $isResource = isset($assoc_args["resource"]);
        $namespace = $assoc_args["namespace"] ?? null;

        try {
            $this->createController($name, $path, $isApi, $isResource, $namespace);
            \WP_CLI::success("Controller {$name} created successfully.");
            \WP_CLI::line("Controller created in: {$path}");

            // Register the path with autoloader if it's not already registered
            ControllerAutoloader::addPath($path, $namespace);

        } catch (\Exception $e) {
            \WP_CLI::error("Controller creation failed: " . $e->getMessage());
        }
    }

    /**
     * Create the controller file
     *
     * @param string $name
     * @param string $path
     * @param bool $isApi
     * @param bool $isResource
     * @param string|null $namespace
     */
    protected function createController($name, $path, $isApi = false, $isResource = false, $namespace = null)
    {
        // Handle nested controllers (e.g., Admin/UserController)
        $pathParts = explode('/', $name);
        $className = array_pop($pathParts);
        $subPath = implode('/', $pathParts);
        
        $fullPath = $path;
        if ($subPath) {
            $fullPath .= '/' . $subPath;
        }
        
        $filename = $fullPath . '/' . $className . '.php';

        if (file_exists($filename)) {
            \WP_CLI::error("Controller {$name} already exists!");
        }

        if (!is_dir($fullPath)) {
            wp_mkdir_p($fullPath);
        }

        $content = $this->getControllerTemplate($className, $isApi, $isResource, $namespace, $subPath);
        file_put_contents($filename, $content);
    }

    /**
     * Get the controller template content
     *
     * @param string $name
     * @param bool $isApi
     * @param bool $isResource
     * @param string|null $namespace
     * @param string $subPath
     * @return string
     */
    protected function getControllerTemplate($name, $isApi = false, $isResource = false, $namespace = null, $subPath = '')
    {
        $namespaceLine = '';
        if ($namespace) {
            if ($subPath) {
                $namespaceLine = "namespace {$namespace}\\" . str_replace('/', '\\', $subPath) . ";";
            } else {
                $namespaceLine = "namespace {$namespace};";
            }
        }
        
        $extends = $isApi ? 'BaseController' : '';
        $extendsLine = $extends ? " extends {$extends}" : '';
        
        $useStatements = '';
        if ($isApi) {
            $useStatements = "use WordPressRoutes\\Routing\\BaseController;\nuse WordPressRoutes\\Routing\\ApiRequest;\n";
        }
        
        $methods = '';
        if ($isResource) {
            $methods = $this->getResourceMethods($isApi);
        } else {
            $methods = $this->getBasicMethods($isApi);
        }

        return "<?php
{$namespaceLine}

{$useStatements}
/**
 * {$name}
 */
class {$name}{$extendsLine}
{
{$methods}
}
";
    }

    /**
     * Get basic controller methods
     */
    protected function getBasicMethods($isApi = false)
    {
        if ($isApi) {
            return '    /**
     * Handle the request
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function handle(ApiRequest $request)
    {
        return $this->success([
            "message" => "Controller method called successfully",
            "data" => $request->all()
        ]);
    }';
        }
        
        return '    /**
     * Handle the request
     */
    public function handle()
    {
        // Controller logic here
        return "Controller method called successfully";
    }';
    }

    /**
     * Get resource controller methods
     */
    protected function getResourceMethods($isApi = false)
    {
        if ($isApi) {
            return '    /**
     * Display a listing of the resource
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function index(ApiRequest $request)
    {
        // Get all resources
        return $this->success([]);
    }

    /**
     * Store a newly created resource
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function store(ApiRequest $request)
    {
        // Create new resource
        return $this->success([], "Resource created successfully", 201);
    }

    /**
     * Display the specified resource
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function show(ApiRequest $request)
    {
        $id = $request->param("id");
        
        // Get specific resource
        return $this->success(["id" => $id]);
    }

    /**
     * Update the specified resource
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function update(ApiRequest $request)
    {
        $id = $request->param("id");
        
        // Update resource
        return $this->success(["id" => $id], "Resource updated successfully");
    }

    /**
     * Remove the specified resource
     *
     * @param ApiRequest $request
     * @return WP_REST_Response
     */
    public function destroy(ApiRequest $request)
    {
        $id = $request->param("id");
        
        // Delete resource
        return $this->success([], "Resource deleted successfully");
    }';
        }
        
        return '    /**
     * Display a listing of the resource
     */
    public function index()
    {
        // Get all resources
    }

    /**
     * Store a newly created resource
     */
    public function store()
    {
        // Create new resource
    }

    /**
     * Display the specified resource
     */
    public function show($id)
    {
        // Get specific resource
    }

    /**
     * Update the specified resource
     */
    public function update($id)
    {
        // Update resource
    }

    /**
     * Remove the specified resource
     */
    public function destroy($id)
    {
        // Delete resource
    }';
    }

    /**
     * List all discovered controllers
     *
     * Shows all controllers found in registered paths
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render output in a particular format (table, csv, json, yaml)
     * ---
     * default: table
     * options:
     *   - table
     *   - csv
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     wp wproutes controller:list
     *     wp wproutes controller:list --format=json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function listControllers($args, $assoc_args)
    {
        try {
            $controllers = ControllerAutoloader::discoverControllers();

            if (empty($controllers)) {
                \WP_CLI::warning("No controller files found in registered paths.");
                \WP_CLI::line("Registered paths:");
                foreach (ControllerAutoloader::getPaths() as $path) {
                    \WP_CLI::line("  - {$path}");
                }
                return;
            }

            $format = $assoc_args['format'] ?? 'table';
            
            // Prepare data for display
            $displayData = array_map(function($controllerFile) {
                $fileName = basename($controllerFile, '.php');
                $pathDir = dirname($controllerFile);
                
                return [
                    'controller' => $fileName,
                    'file' => $controllerFile,
                    'path' => $pathDir
                ];
            }, $controllers);

            \WP_CLI\Utils\format_items($format, $displayData, ['controller', 'file', 'path']);
            
            // Summary
            $total = count($controllers);
            $pathCount = count(array_unique(array_column($displayData, 'path')));
            
            \WP_CLI::line("");
            \WP_CLI::line("Summary: {$total} controllers found across {$pathCount} directories");
        } catch (\Exception $e) {
            \WP_CLI::error("Failed to list controllers: " . $e->getMessage());
        }
    }

    /**
     * List all registered routes
     *
     * ## EXAMPLES
     *
     *     wp wproutes route:list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function listRoutes($args, $assoc_args)
    {
        \WP_CLI::line("Registered Routes:");
        \WP_CLI::line("");
        
        // Ensure routes are loaded first
        if (function_exists('wproutes_auto_load_routes')) {
            wproutes_auto_load_routes();
        }
        
        // Get registered routes from ApiManager
        if (class_exists('\\WordPressRoutes\\Routing\\ApiManager')) {
            $routes = \WordPressRoutes\Routing\ApiManager::getRoutes();
            
            if (empty($routes)) {
                \WP_CLI::line("No routes registered.");
                return;
            }
            
            foreach ($routes as $route) {
                // Handle ApiRoute objects properly
                if (is_object($route) && method_exists($route, 'getMethods')) {
                    $methods = $route->getMethods();
                    $namespace = $route->getNamespace();
                    $endpoint = $route->getEndpoint();
                    
                    // Get callback using reflection
                    try {
                        $reflection = new \ReflectionClass($route);
                        $callbackProperty = $reflection->getProperty('callback');
                        $callbackProperty->setAccessible(true);
                        $callback = $callbackProperty->getValue($route);
                        
                        if (is_string($callback)) {
                            $callbackStr = $callback;
                        } else {
                            $callbackStr = 'Custom Handler';
                        }
                    } catch (\Exception $e) {
                        $callbackStr = 'Handler';
                    }
                    
                    foreach ($methods as $method) {
                        $path = "/wp-json/{$namespace}/{$endpoint}";
                        \WP_CLI::line(sprintf("%-6s %-40s %s", strtoupper($method), $path, $callbackStr));
                    }
                } else {
                    // Fallback for array format (legacy support)
                    $method = strtoupper($route['method'] ?? 'GET');
                    $path = $route['path'] ?? '';
                    $callback = $route['callback'] ?? '';
                    
                    \WP_CLI::line(sprintf("%-6s %-40s %s", $method, $path, $callback));
                }
            }
        } else {
            \WP_CLI::warning("ApiManager class not found. Make sure WordPress Routes is loaded.");
        }
    }
}