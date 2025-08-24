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
     *     wp borps routes:make-controller ProductController
     *     wp borps routes:make-controller ProductController --api
     *     wp borps routes:make-controller ProductController --resource
     *     wp borps routes:make-controller Admin/UserController --namespace="MyApp\\Controllers"
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
        if (!str_ends_with($name, "Controller")) {
            $name .= "Controller";
        }

        // Default path: use mode-based controllers directory (create if doesn't exist)
        $defaultPath = wproutes_get_default_path("controllers");

        $path = $assoc_args["path"] ?? $defaultPath;
        $isApi = isset($assoc_args["api"]);
        $isResource = isset($assoc_args["resource"]);
        $namespace = $assoc_args["namespace"] ?? null;

        try {
            $this->createController(
                $name,
                $path,
                $isApi,
                $isResource,
                $namespace,
            );
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
    protected function createController(
        $name,
        $path,
        $isApi = false,
        $isResource = false,
        $namespace = null,
    ) {
        // Handle nested controllers (e.g., Admin/UserController)
        $pathParts = explode("/", $name);
        $className = array_pop($pathParts);
        $subPath = implode("/", $pathParts);

        $fullPath = $path;
        if ($subPath) {
            $fullPath .= "/" . $subPath;
        }

        $filename = $fullPath . "/" . $className . ".php";

        if (file_exists($filename)) {
            \WP_CLI::error("Controller {$name} already exists!");
        }

        if (!is_dir($fullPath)) {
            wp_mkdir_p($fullPath);
        }

        $content = $this->getControllerTemplate(
            $className,
            $isApi,
            $isResource,
            $namespace,
            $subPath,
        );
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
    protected function getControllerTemplate(
        $name,
        $isApi = false,
        $isResource = false,
        $namespace = null,
        $subPath = "",
    ) {
        $namespaceLine = "";
        if ($namespace) {
            if ($subPath) {
                $namespaceLine =
                    "namespace {$namespace}\\" .
                    str_replace("/", "\\", $subPath) .
                    ";";
            } else {
                $namespaceLine = "namespace {$namespace};";
            }
        }

        $extends = $isApi ? "BaseController" : "";
        $extendsLine = $extends ? " extends {$extends}" : "";

        $useStatements = "";
        if ($isApi) {
            $useStatements =
                "use WordPressRoutes\\Routing\\BaseController;\nuse WordPressRoutes\\Routing\\RouteRequest;\n";
        }

        $methods = "";
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
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function handle(RouteRequest $request)
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
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function index(RouteRequest $request)
    {
        // Get all resources
        return $this->success([]);
    }

    /**
     * Store a newly created resource
     *
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function store(RouteRequest $request)
    {
        // Create new resource
        return $this->success([], "Resource created successfully", 201);
    }

    /**
     * Display the specified resource
     *
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function show(RouteRequest $request)
    {
        $id = $request->param("id");

        // Get specific resource
        return $this->success(["id" => $id]);
    }

    /**
     * Update the specified resource
     *
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function update(RouteRequest $request)
    {
        $id = $request->param("id");

        // Update resource
        return $this->success(["id" => $id], "Resource updated successfully");
    }

    /**
     * Remove the specified resource
     *
     * @param RouteRequest $request
     * @return WP_REST_Response
     */
    public function destroy(RouteRequest $request)
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
     *     wp borps routes:controller-list
     *     wp borps routes:controller-list --format=json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function listControllers($args, $assoc_args)
    {
        try {
            $controllers = ControllerAutoloader::discoverControllers();

            if (empty($controllers)) {
                \WP_CLI::warning(
                    "No controller files found in registered paths.",
                );
                \WP_CLI::line("Registered paths:");
                foreach (ControllerAutoloader::getPaths() as $path) {
                    \WP_CLI::line("  - {$path}");
                }
                return;
            }

            $format = $assoc_args["format"] ?? "table";

            // Prepare data for display
            $displayData = array_map(function ($controllerFile) {
                $fileName = basename($controllerFile, ".php");
                $pathDir = dirname($controllerFile);

                return [
                    "controller" => $fileName,
                    "file" => $controllerFile,
                    "path" => $pathDir,
                ];
            }, $controllers);

            \WP_CLI\Utils\format_items($format, $displayData, [
                "controller",
                "file",
                "path",
            ]);

            // Summary
            $total = count($controllers);
            $pathCount = count(
                array_unique(array_column($displayData, "path")),
            );

            \WP_CLI::line("");
            \WP_CLI::line(
                "Summary: {$total} controllers found across {$pathCount} directories",
            );
        } catch (\Exception $e) {
            \WP_CLI::error("Failed to list controllers: " . $e->getMessage());
        }
    }

    /**
     * List all registered routes categorized by type
     *
     * ## EXAMPLES
     *
     *     wp borps routes:list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function listRoutes($args, $assoc_args)
    {
        // Ensure routes are loaded first
        if (function_exists("wproutes_auto_load_routes")) {
            wproutes_auto_load_routes();
        }

        // Get registered routes from RouteManager
        if (!class_exists("\\WordPressRoutes\\Routing\\RouteManager")) {
            \WP_CLI::warning(
                "RouteManager class not found. Make sure WordPress Routes is loaded.",
            );
            return;
        }

        $routes = \WordPressRoutes\Routing\RouteManager::getRoutes();

        if (empty($routes)) {
            \WP_CLI::line("No routes registered.");
            return;
        }

        // Categorize routes by type
        $routesByType = [
            "api" => [],
            "web" => [],
            "admin" => [],
            "ajax" => [],
        ];

        foreach ($routes as $route) {
            if (is_object($route) && method_exists($route, "getType")) {
                $type = $route->getType();
                if (isset($routesByType[$type])) {
                    $routesByType[$type][] = $route;
                }
            }
        }

        // Display API Routes
        if (!empty($routesByType["api"])) {
            \WP_CLI::line("API Routes (REST API):");
            \WP_CLI::line("");
            foreach ($routesByType["api"] as $route) {
                $this->displayApiRoute($route);
            }
            \WP_CLI::line("");
        }

        // Display Web Routes
        if (!empty($routesByType["web"])) {
            \WP_CLI::line("Web Routes (Frontend Pages):");
            \WP_CLI::line("");
            foreach ($routesByType["web"] as $route) {
                $this->displayWebRoute($route);
            }
            \WP_CLI::line("");
        }

        // Display Admin Routes
        if (!empty($routesByType["admin"])) {
            \WP_CLI::line("Admin Routes (Dashboard Pages):");
            \WP_CLI::line("");
            foreach ($routesByType["admin"] as $route) {
                $this->displayAdminRoute($route);
            }
            \WP_CLI::line("");
        }

        // Display AJAX Routes
        if (!empty($routesByType["ajax"])) {
            \WP_CLI::line("AJAX Routes (AJAX Handlers):");
            \WP_CLI::line("");
            foreach ($routesByType["ajax"] as $route) {
                $this->displayAjaxRoute($route);
            }
            \WP_CLI::line("");
        }

        // Summary
        $totalRoutes = count($routes);
        $apiCount = count($routesByType["api"]);
        $webCount = count($routesByType["web"]);
        $adminCount = count($routesByType["admin"]);
        $ajaxCount = count($routesByType["ajax"]);

        \WP_CLI::line("Summary:");
        \WP_CLI::line("  Total Routes: {$totalRoutes}");
        \WP_CLI::line("  API Routes: {$apiCount}");
        \WP_CLI::line("  Web Routes: {$webCount}");
        \WP_CLI::line("  Admin Routes: {$adminCount}");
        \WP_CLI::line("  AJAX Routes: {$ajaxCount}");
    }

    /**
     * Display an API route
     */
    private function displayApiRoute($route)
    {
        $methods = $route->getMethods();
        $namespace = $route->getNamespace() ?: "wp/v2";
        $endpoint = $route->getEndpoint();
        $callback = $this->getCallbackString($route);

        foreach ($methods as $method) {
            $path = "/wp-json/{$namespace}/{$endpoint}";
            \WP_CLI::line(
                sprintf(
                    "  %-6s %-50s %s",
                    strtoupper($method),
                    $path,
                    $callback,
                ),
            );
        }
    }

    /**
     * Display a Web route
     */
    private function displayWebRoute($route)
    {
        $endpoint = $route->getEndpoint();
        $callback = $this->getCallbackString($route);
        $path = "/{$endpoint}";

        \WP_CLI::line(sprintf("  %-6s %-50s %s", "GET", $path, $callback));
    }

    /**
     * Display an Admin route
     */
    private function displayAdminRoute($route)
    {
        $endpoint = $route->getEndpoint();
        $callback = $this->getCallbackString($route);
        $path = "/wp-admin/admin.php?page={$endpoint}";

        \WP_CLI::line(sprintf("  %-6s %-50s %s", "ADMIN", $path, $callback));
    }

    /**
     * Display an AJAX route
     */
    private function displayAjaxRoute($route)
    {
        $endpoint = $route->getEndpoint();
        $callback = $this->getCallbackString($route);
        $path = "/wp-admin/admin-ajax.php?action={$endpoint}";

        \WP_CLI::line(sprintf("  %-6s %-50s %s", "AJAX", $path, $callback));
    }

    /**
     * Get callback string from route
     */
    private function getCallbackString($route)
    {
        try {
            $reflection = new \ReflectionClass($route);
            $callbackProperty = $reflection->getProperty("callback");
            $callbackProperty->setAccessible(true);
            $callback = $callbackProperty->getValue($route);

            if (is_string($callback)) {
                return $callback;
            }

            return "Custom Handler";
        } catch (\Exception $e) {
            return "Handler";
        }
    }

    /**
     * Flush WordPress rewrite rules for web routes
     *
     * ## EXAMPLES
     *
     *     wp borps routes:flush
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function flushRoutes($args, $assoc_args)
    {
        \WP_CLI::line("Flushing WordPress rewrite rules...");

        flush_rewrite_rules(true);

        \WP_CLI::success("Rewrite rules have been flushed successfully.");
        \WP_CLI::line("Your web routes should now be working properly.");
        \WP_CLI::line("");
        \WP_CLI::line(
            "Note: Run this command whenever you add or modify web routes.",
        );
    }

    /**
     * Debug web routes and rewrite rules
     *
     * ## EXAMPLES
     *
     *     wp borps routes:debug
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function debugRoutes($args, $assoc_args)
    {
        global $wp_rewrite;

        \WP_CLI::line("Debugging WordPress Routes...");
        \WP_CLI::line("");

        // Ensure routes are loaded
        if (function_exists("wproutes_auto_load_routes")) {
            wproutes_auto_load_routes();
        }

        // Get web routes
        $routes = \WordPressRoutes\Routing\RouteManager::getRoutes();
        $webRoutes = array_filter($routes, function ($route) {
            return $route->getType() ===
                \WordPressRoutes\Routing\Route::TYPE_WEB;
        });

        \WP_CLI::line("Web Routes Found: " . count($webRoutes));
        foreach ($webRoutes as $route) {
            \WP_CLI::line("  - /" . $route->getEndpoint());
        }
        \WP_CLI::line("");

        // Check rewrite rules
        \WP_CLI::line("WordPress Rewrite Rules:");
        $rules = get_option("rewrite_rules");

        if ($rules) {
            $routeRules = array_filter($rules, function ($redirect) {
                return strpos($redirect, "route_handler") !== false;
            });

            if (empty($routeRules)) {
                \WP_CLI::warning("No route_handler rewrite rules found!");
                \WP_CLI::line(
                    "This means web routes aren't being registered properly.",
                );

                // Show some sample rewrite rules for comparison
                \WP_CLI::line("");
                \WP_CLI::line("Sample of existing rewrite rules:");
                $sampleRules = array_slice($rules, 0, 5, true);
                foreach ($sampleRules as $pattern => $redirect) {
                    \WP_CLI::line("  {$pattern} => {$redirect}");
                }
                \WP_CLI::line(
                    "  ... and " . (count($rules) - 5) . " more rules",
                );
            } else {
                \WP_CLI::line("Route rewrite rules found:");
                foreach ($routeRules as $pattern => $redirect) {
                    \WP_CLI::line("  {$pattern} => {$redirect}");
                }
            }
        } else {
            \WP_CLI::warning("No rewrite rules found!");
        }

        // Check debug info if available
        global $wp_routes_debug;
        if (!empty($wp_routes_debug["rewrite_rules"])) {
            \WP_CLI::line("");
            \WP_CLI::line("Route Registration Debug Info:");
            foreach ($wp_routes_debug["rewrite_rules"] as $rule) {
                \WP_CLI::line("  Endpoint: /{$rule["endpoint"]}");
                \WP_CLI::line("  Regex: {$rule["regex"]}");
                \WP_CLI::line("  Redirect: {$rule["redirect"]}");
                \WP_CLI::line("  ---");
            }
        }

        \WP_CLI::line("");
        \WP_CLI::line("Recommendations:");
        \WP_CLI::line("1. Run: wp borps routes:route:flush --allow-root");
        \WP_CLI::line("2. Check if routes.php is being loaded");
        \WP_CLI::line("3. Verify WordPress permalink structure is not 'Plain'");
    }
}
