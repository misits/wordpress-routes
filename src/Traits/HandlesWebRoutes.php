<?php

namespace WordPressRoutes\Routing\Traits;

use WordPressRoutes\Routing\RouteRequest;
use WordPressRoutes\Routing\ControllerAutoloader;
use WordPressRoutes\Routing\VirtualPage;

defined("ABSPATH") or exit();

/**
 * Trait for handling Web routes - Using universal RouteRequest
 *
 * @since 1.0.0
 */
trait HandlesWebRoutes
{
    /**
     * Matched parameters from current route
     */
    private array $matchedParams = [];

    /**
     * Register web route
     */
    protected function registerWebRoute(): void
    {
        // Register the route to be processed on wp_loaded with lower priority
        // This ensures API/webhook routes are processed first
        add_action('wp_loaded', [$this, 'processWebRoute'], 15);
        
        // Set title if provided
        if ($this->webSettings['title']) {
            add_filter('document_title_parts', [$this, 'setWebTitle'], 10);
        }
    }

    /**
     * Process web route - main entry point
     */
    public function processWebRoute(): void
    {
        if (!$this->matchesCurrentPath()) {
            return;
        }
        
        // Create universal RouteRequest object
        $request = $this->createWebRequest();
        
        // Process middleware first
        foreach ($this->middleware as $middleware) {
            if (!$this->processWebMiddleware($middleware, $request)) {
                wp_die('Access Denied', 403);
            }
        }
        
        // Execute the callback and handle response
        try {
            // Use output buffering to catch any direct output
            ob_start();
            $result = $this->executeWebCallback($request);
            $output = ob_get_contents();
            ob_end_clean();
            
            // If callback returned content (like from wproutes_view), output and exit
            if ($result !== null && is_string($result) && !empty(trim($result))) {
                echo $result;
                exit(); // Stop WordPress from continuing
            }
            
            // If callback generated output via echo/get_header/get_footer, output and exit
            if ($output !== null && !empty(trim($output))) {
                echo $output;
                exit(); // Stop WordPress from continuing
            }
            
        } catch (\Exception $e) {
            wp_die('Route Error: ' . $e->getMessage(), 500);
        }
        
        // If no content was returned, create virtual page fallback
        if ($this->webSettings['template']) {
            $title = $this->getPageTitle();
            $virtualPage = new VirtualPage($this->webSettings['template'], $title);
            $virtualPage->setUri($this->endpoint)->process();
        }
    }
    
    /**
     * Check if current path matches this route
     */
    private function matchesCurrentPath(): bool
    {
        $currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
        $routePath = trim($this->originalEndpoint ?? $this->endpoint, '/');
        
        // If no parameters, use exact match
        if (strpos($routePath, '{') === false) {
            return $currentPath === $routePath;
        }
        
        // Convert route pattern to regex for parameter matching
        $pattern = $this->convertRouteToRegex($routePath);
        
        // Test if current path matches the pattern
        if (preg_match($pattern, $currentPath, $matches)) {
            // Store matched parameters for later use
            $this->matchedParams = $matches;
            return true;
        }
        
        return false;
    }
    
    /**
     * Convert route pattern to regex
     */
    private function convertRouteToRegex(string $route): string
    {
        // Escape special regex characters except our parameter brackets
        $pattern = preg_quote($route, '#');
        
        // Convert {param} to named capturing groups
        $pattern = preg_replace('/\\\\{([^}]+)\\\\}/', '(?P<$1>[^/]+)', $pattern);
        
        // Make the pattern match the entire path
        return '#^' . $pattern . '$#';
    }

    /**
     * Create universal RouteRequest object for web routes
     */
    protected function createWebRequest(): RouteRequest
    {
        $params = [];
        
        // Extract parameters from matched URL pattern
        if (!empty($this->matchedParams)) {
            foreach ($this->params as $paramName => $paramInfo) {
                if (isset($this->matchedParams[$paramName])) {
                    $params[$paramName] = $this->matchedParams[$paramName];
                }
            }
        }
        
        return RouteRequest::createForWeb($params);
    }

    /**
     * Execute web route callback - matches API route pattern
     */
    protected function executeWebCallback($request): mixed
    {
        if (is_string($this->callback) && strpos($this->callback, '@') !== false) {
            // Handle Controller@method syntax
            [$class, $method] = explode('@', $this->callback, 2);
            $controller = ControllerAutoloader::resolve($class);
            
            if (!$controller) {
                throw new \Exception("Controller '{$class}' not found");
            }
            
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method '{$method}' not found in controller '{$class}'");
            }
            
            return $controller->$method($request);
        }
        
        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $request);
        }
        
        throw new \Exception("Invalid callback");
    }
    
    /**
     * Process web middleware
     */
    protected function processWebMiddleware(string|callable $middleware, RouteRequest $request): bool
    {
        return match (true) {
            $middleware === 'auth' => $request->isAuthenticated() ?: (auth_redirect() || false),
            str_starts_with($middleware, 'can:') => current_user_can(substr($middleware, 4)),
            is_callable($middleware) => $middleware($request) !== false,
            default => true
        };
    }

    /**
     * Get page title for virtual page
     */
    private function getPageTitle(): string
    {
        if (is_callable($this->webSettings['title'])) {
            $request = $this->createWebRequest();
            return (string) call_user_func($this->webSettings['title'], $request);
        }
        
        return (string) ($this->webSettings['title'] ?? ucfirst($this->endpoint));
    }

    /**
     * Set web page title
     */
    public function setWebTitle(array $title): array
    {
        if (!$this->matchesCurrentPath()) {
            return $title;
        }

        $title['title'] = $this->getPageTitle();
        return $title;
    }

    /**
     * Set page template (web routes)
     */
    public function template(string $template): self
    {
        $this->webSettings['template'] = $template;
        return $this;
    }

    /**
     * Set page title (web routes)
     */
    public function title(string|callable $title): self
    {
        $this->webSettings['title'] = $title;
        return $this;
    }

    /**
     * Set route priority (web routes)
     */
    public function priority(string $priority = 'top'): self
    {
        $this->webSettings['priority'] = $priority;
        return $this;
    }
}