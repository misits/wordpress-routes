<?php

namespace WordPressRoutes\CLI\WP;

use \WP_CLI_Command;
use \WP_CLI;

/**
 * WordPress Routes Middleware commands for WP-CLI
 */
class MiddlewareCommand extends \WP_CLI_Command
{
    /**
     * Generate a new middleware class
     *
     * ## OPTIONS
     *
     * <name>
     * : Name of the middleware
     *
     * [--path=<path>]
     * : Path to middleware directory (optional - will auto-detect based on mode)
     *
     * [--namespace=<namespace>]
     * : Specify namespace for the middleware (optional)
     *
     * ## EXAMPLES
     *
     *     wp borps routes:make-middleware AuthMiddleware
     *     wp borps routes:make-middleware CustomAuthMiddleware --namespace="MyApp\\Middleware"
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error("Middleware name is required.");
        }

        $name = $args[0];
        
        // Ensure name ends with 'Middleware'
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }
        
        // Default path: use mode-based middleware directory
        $defaultPath = wproutes_get_default_path('middleware');
        
        $path = $assoc_args["path"] ?? $defaultPath;
        $namespace = $assoc_args["namespace"] ?? null;

        try {
            $this->createMiddleware($name, $path, $namespace);
            \WP_CLI::success("Middleware {$name} created successfully.");
            \WP_CLI::line("Middleware created in: {$path}");

        } catch (\Exception $e) {
            \WP_CLI::error("Middleware creation failed: " . $e->getMessage());
        }
    }

    /**
     * Create the middleware file
     *
     * @param string $name
     * @param string $path
     * @param string|null $namespace
     */
    protected function createMiddleware($name, $path, $namespace = null)
    {
        $filename = $path . '/' . $name . '.php';

        if (file_exists($filename)) {
            \WP_CLI::error("Middleware {$name} already exists!");
        }

        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        $content = $this->getMiddlewareTemplate($name, $namespace);
        file_put_contents($filename, $content);
    }

    /**
     * Get the middleware template content
     *
     * @param string $name
     * @param string|null $namespace
     * @return string
     */
    protected function getMiddlewareTemplate($name, $namespace = null)
    {
        $namespaceLine = $namespace ? "namespace {$namespace};" : '';
        
        return "<?php
{$namespaceLine}

use WordPressRoutes\\Routing\\Middleware\\MiddlewareInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * {$name}
 */
class {$name} implements MiddlewareInterface
{
    /**
     * Handle the middleware
     *
     * @param WP_REST_Request \$request
     * @param callable \$next
     * @return WP_REST_Response|mixed
     */
    public function handle(WP_REST_Request \$request, callable \$next)
    {
        // Pre-processing logic here
        // Example: Authentication, rate limiting, logging, etc.
        
        // Check some condition
        if (!\$this->checkCondition(\$request)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Middleware condition failed'
            ], 403);
        }
        
        // Continue to the next middleware or controller
        \$response = \$next(\$request);
        
        // Post-processing logic here
        // Example: Modify response, log results, etc.
        
        return \$response;
    }
    
    /**
     * Check middleware condition
     *
     * @param WP_REST_Request \$request
     * @return bool
     */
    protected function checkCondition(WP_REST_Request \$request): bool
    {
        // Implement your middleware logic here
        // Return true to continue, false to block
        
        return true;
    }
}
";
    }

    /**
     * List all discovered middleware
     *
     * Shows all middleware found in registered paths
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
     *     wp borps routes:middleware-list
     *     wp borps routes:middleware-list --format=json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function listMiddleware($args, $assoc_args)
    {
        try {
            // Get default middleware path
            $middlewarePath = wproutes_get_default_path('middleware');
            $middlewareFiles = [];
            
            if (is_dir($middlewarePath)) {
                $files = glob($middlewarePath . '/*.php');
                foreach ($files as $file) {
                    if (strpos(basename($file), 'Middleware') !== false) {
                        $middlewareFiles[] = $file;
                    }
                }
            }
            
            // Also check built-in middleware
            $builtinPath = WPROUTES_SRC_DIR . '/Middleware';
            if (is_dir($builtinPath)) {
                $builtinFiles = glob($builtinPath . '/*.php');
                foreach ($builtinFiles as $file) {
                    if (basename($file) !== 'MiddlewareInterface.php') {
                        $middlewareFiles[] = $file;
                    }
                }
            }

            if (empty($middlewareFiles)) {
                \WP_CLI::warning("No middleware files found.");
                \WP_CLI::line("Searched paths:");
                \WP_CLI::line("  - {$middlewarePath}");
                \WP_CLI::line("  - {$builtinPath}");
                return;
            }

            $format = $assoc_args['format'] ?? 'table';
            
            // Prepare data for display
            $displayData = array_map(function($middlewareFile) {
                $fileName = basename($middlewareFile, '.php');
                $pathDir = dirname($middlewareFile);
                $type = strpos($pathDir, '/lib/wp-routes/') !== false ? 'Built-in' : 'User';
                
                return [
                    'middleware' => $fileName,
                    'type' => $type,
                    'file' => $middlewareFile,
                    'path' => $pathDir
                ];
            }, $middlewareFiles);

            \WP_CLI\Utils\format_items($format, $displayData, ['middleware', 'type', 'file', 'path']);
            
            // Summary
            $total = count($middlewareFiles);
            $pathCount = count(array_unique(array_column($displayData, 'path')));
            
            \WP_CLI::line("");
            \WP_CLI::line("Summary: {$total} middleware found across {$pathCount} directories");
        } catch (\Exception $e) {
            \WP_CLI::error("Failed to list middleware: " . $e->getMessage());
        }
    }
}