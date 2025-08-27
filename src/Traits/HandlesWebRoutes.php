<?php

namespace WordPressRoutes\Routing\Traits;

use WordPressRoutes\Routing\VirtualPage;

defined("ABSPATH") or exit();

/**
 * Trait for handling Web routes - Simplified approach using VirtualPage
 *
 * @since 1.0.0
 */
trait HandlesWebRoutes
{
    /**
     * Register web route using simplified approach
     */
    protected function registerWebRoute(): void
    {
        // Register the route to be processed on wp_loaded
        add_action('wp_loaded', [$this, 'processWebRoute'], 10);
        
        // Set title if provided
        if ($this->webSettings['title']) {
            add_filter('document_title_parts', [$this, 'setWebTitle'], 10);
        }
    }

    /**
     * Process web route using simplified approach
     */
    public function processWebRoute(): void
    {
        if (!$this->matchesCurrentPath()) {
            return;
        }
        
        // Check for early redirects before WordPress starts outputting content
        $request = $this->createWebRequest();
        
        // Process middleware first
        foreach ($this->middleware as $middleware) {
            if (!$this->processWebMiddleware($middleware, $request)) {
                wp_die('Access Denied', 403);
            }
        }
        
        // Check if callback wants to do a redirect by testing it early
        if (is_callable($this->callback)) {
            // Use output buffering to catch any redirect attempts
            ob_start();
            
            try {
                call_user_func($this->callback, $request);
                $output = ob_get_contents();
                
                // If callback generated content, it handled everything (including redirects)
                if (!empty(trim($output))) {
                    ob_end_clean();
                    echo $output;
                    exit(); // Stop WordPress from continuing
                }
                
                ob_end_clean();
            } catch (Exception $e) {
                ob_end_clean();
                // If callback called exit() or wp_redirect(), we handle it gracefully
                return;
            }
        }
        
        // Create and configure virtual page for normal content
        $title = $this->getPageTitle();
        $template = $this->webSettings['template'];
        
        $virtualPage = new VirtualPage($template, $title);
        $virtualPage->setUri($this->endpoint)
                   ->setCallback([$this, 'executeWebRoute']);
        
        // Process the virtual page
        $virtualPage->process();
    }
    
    /**
     * Execute web route callback with middleware
     */
    public function executeWebRoute(): mixed
    {
        $request = $this->createWebRequest();
        
        // Process middleware
        foreach ($this->middleware as $middleware) {
            if (!$this->processWebMiddleware($middleware, $request)) {
                wp_die('Access Denied', 403);
            }
        }
        
        // Execute callback
        if (is_callable($this->callback)) {
            return call_user_func($this->callback, $request);
        }
        
        return null;
    }
    
    /**
     * Check if current path matches this route
     */
    private function matchesCurrentPath(): bool
    {
        $currentPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
        $routePath = trim($this->endpoint, '/');
        
        return $currentPath === $routePath;
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
     * Process web middleware
     */
    protected function processWebMiddleware(string|callable $middleware, array $request): bool
    {
        return match (true) {
            $middleware === 'auth' => $request['is_authenticated'] ?: (auth_redirect() || false),
            str_starts_with($middleware, 'can:') => current_user_can(substr($middleware, 4)),
            is_callable($middleware) => $middleware($request) !== false,
            default => true
        };
    }

    /**
     * Create web request
     */
    protected function createWebRequest(): array
    {
        $params = [];
        foreach ($this->params as $param => $original) {
            $params[$param] = get_query_var($param, '');
        }
        
        return [
            'params' => $params,
            'query' => $_GET,
            'post' => $_POST,
            'user' => wp_get_current_user(),
            'is_authenticated' => is_user_logged_in()
        ];
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