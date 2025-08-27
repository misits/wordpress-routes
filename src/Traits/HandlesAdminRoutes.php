<?php

namespace WordPressRoutes\Routing\Traits;

defined("ABSPATH") or exit();

/**
 * Trait for handling Admin routes
 *
 * @since 1.0.0
 */
trait HandlesAdminRoutes
{
    /**
     * Register admin route
     */
    protected function registerAdminRoute()
    {
        add_action('admin_menu', function() {
            if ($this->adminSettings['parent']) {
                add_submenu_page(
                    $this->adminSettings['parent'],
                    $this->adminSettings['page_title'],
                    $this->adminSettings['menu_title'] ?: $this->adminSettings['page_title'],
                    $this->adminSettings['capability'],
                    $this->endpoint,
                    [$this, 'handleAdminRequest']
                );
            } else {
                add_menu_page(
                    $this->adminSettings['page_title'],
                    $this->adminSettings['menu_title'] ?: $this->adminSettings['page_title'],
                    $this->adminSettings['capability'],
                    $this->endpoint,
                    [$this, 'handleAdminRequest'],
                    $this->adminSettings['icon'],
                    $this->adminSettings['position']
                );
            }
        });
    }

    /**
     * Handle admin request
     */
    public function handleAdminRequest(): void
    {
        if (!current_user_can($this->adminSettings['capability'])) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $request = $this->createAdminRequest();

        // Process middleware
        foreach ($this->middleware as $middleware) {
            if (!$this->processAdminMiddleware($middleware, $request)) {
                wp_die('Access Denied', 403);
            }
        }

        // Execute callback to get data
        $response = $this->executeAdminCallback($request);

        // Render with template if provided, otherwise use default rendering
        if ($this->adminSettings['template']) {
            $this->renderAdminTemplate($response, $request);
        } else {
            $this->renderAdminResponse($response);
        }
    }

    /**
     * Process admin middleware
     */
    protected function processAdminMiddleware(string|callable $middleware, array $request): bool
    {
        return match (true) {
            $middleware === 'auth' => $request['is_authenticated'] ?: (auth_redirect() || false),
            str_starts_with($middleware, 'can:') => current_user_can(substr($middleware, 4)),
            is_callable($middleware) => $middleware($request) !== false,
            default => true
        };
    }

    /**
     * Execute admin callback
     */
    protected function executeAdminCallback(array $request): mixed
    {
        return is_callable($this->callback) ? call_user_func($this->callback, $request) : null;
    }

    /**
     * Create admin request
     */
    protected function createAdminRequest(): array
    {
        return [
            'get' => $_GET,
            'post' => $_POST,
            'request' => $_REQUEST,
            'user' => wp_get_current_user(),
            'is_authenticated' => is_user_logged_in(),
            'capability' => $this->adminSettings['capability'],
            'slug' => $this->endpoint,
            'nonce' => wp_create_nonce('admin_' . $this->endpoint),
            'ajax_url' => admin_url('admin-ajax.php'),
            'admin_url' => admin_url('admin.php?page=' . $this->endpoint)
        ];
    }

    /**
     * Render admin response using template file
     */
    protected function renderAdminTemplate(mixed $response, array $request): void
    {
        $templatePath = $this->findAdminTemplate($this->adminSettings['template']);
        
        if (!$templatePath) {
            // Fallback to default rendering if template not found
            $this->renderAdminResponse($response);
            return;
        }

        // Make data available to template
        if (is_array($response)) {
            $GLOBALS['admin_route_data'] = $response;
            // Extract variables for direct access in template
            extract($response, EXTR_SKIP);
        }
        
        // Make request data available
        $GLOBALS['admin_route_request'] = $request;
        
        // Include the template
        include $templatePath;
    }

    /**
     * Render admin response (default method)
     */
    protected function renderAdminResponse(mixed $response): void
    {
        echo '<div class="wrap">';
        
        // Handle different response types
        match (true) {
            is_array($response) => $this->renderArrayResponse($response),
            is_string($response) => $this->renderStringResponse($response),
            default => $this->renderDefaultResponse()
        };

        echo '</div>';
    }
    
    /**
     * Render array response
     */
    private function renderArrayResponse(array $response): void
    {
        $title = $response['title'] ?? $this->adminSettings['page_title'];
        echo '<h1>' . esc_html($title) . '</h1>';

        if (isset($response['content'])) {
            echo $response['content'];
        }

        if (isset($response['tabs']) && is_array($response['tabs'])) {
            $this->renderAdminTabs($response['tabs']);
        }
    }
    
    /**
     * Render string response
     */
    private function renderStringResponse(string $response): void
    {
        echo '<h1>' . esc_html($this->adminSettings['page_title']) . '</h1>';
        echo $response;
    }
    
    /**
     * Render default response
     */
    private function renderDefaultResponse(): void
    {
        echo '<h1>' . esc_html($this->adminSettings['page_title']) . '</h1>';
        echo '<p>No content provided for this admin page.</p>';
    }
    
    /**
     * Find admin template file - supports both theme and plugin modes
     */
    private function findAdminTemplate(string $template): ?string
    {
        // Normalize template name (auto-append .php if missing)
        $normalizedTemplate = $this->normalizeTemplateName($template);
        
        // If it's an absolute path, use it directly
        if (str_starts_with($normalizedTemplate, '/') && file_exists($normalizedTemplate)) {
            return $normalizedTemplate;
        }
        
        $mode = defined("WPROUTES_MODE")
            ? WPROUTES_MODE
            : (defined("WPORM_MODE")
                ? WPORM_MODE
                : "theme");
                
        if ($mode === "plugin") {
            return $this->findPluginAdminTemplate($normalizedTemplate);
        }
        
        // Theme mode (default)
        return $this->findThemeAdminTemplate($normalizedTemplate);
    }
    
    /**
     * Find admin template in plugin mode
     */
    private function findPluginAdminTemplate(string $template): ?string
    {
        // Get plugin directory
        $pluginDir = $this->getPluginDirectory();
        
        if (!$pluginDir) {
            // Fallback to theme mode if plugin dir not found
            return $this->findThemeAdminTemplate($template);
        }
        
        $templatePaths = [];
        
        // If template contains path, use it directly from plugin root
        if (str_contains($template, '/')) {
            $templatePaths[] = $pluginDir . $template;
            $templatePaths[] = $pluginDir . 'templates/' . $template;
            $templatePaths[] = $pluginDir . 'admin-templates/' . $template;
        } else {
            // Search in plugin template directories
            $templatePaths = [
                $pluginDir . 'admin-templates/' . $template,
                $pluginDir . 'templates/admin/' . $template,
                $pluginDir . 'templates/' . $template,
                $pluginDir . 'views/admin/' . $template,
                $pluginDir . 'views/' . $template,
                $pluginDir . $template,
            ];
        }
        
        foreach ($templatePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Fallback to theme
        return $this->findThemeAdminTemplate($template);
    }
    
    /**
     * Find admin template in theme mode
     */
    private function findThemeAdminTemplate(string $template): ?string
    {
        // Priority 1: Check if template contains path, use directly from theme root
        if (str_contains($template, '/')) {
            // Child theme first
            if (get_template_directory() !== get_stylesheet_directory()) {
                $childTemplate = get_stylesheet_directory() . '/' . $template;
                if (file_exists($childTemplate)) {
                    return $childTemplate;
                }
            }
            
            // Parent theme
            $themeTemplate = get_template_directory() . '/' . $template;
            if (file_exists($themeTemplate)) {
                return $themeTemplate;
            }
        } else {
            // Priority 2: Use WordPress's locate_template for simple filenames
            $templatePath = locate_template($template);
            if ($templatePath) {
                return $templatePath;
            }
            
            // Priority 3: Check theme root directly
            $themeTemplate = get_template_directory() . '/' . $template;
            if (file_exists($themeTemplate)) {
                return $themeTemplate;
            }
        }
        
        return null;
    }
    
    /**
     * Get plugin directory for admin templates
     */
    private function getPluginDirectory(): ?string
    {
        // Try to find plugin directory from backtrace
        $backtrace = debug_backtrace();
        foreach ($backtrace as $trace) {
            if (
                isset($trace["file"]) &&
                strpos($trace["file"], "/wp-content/plugins/") !== false
            ) {
                return plugin_dir_path($trace["file"]);
            }
        }
        
        // Fallback: assume we're in a plugin structure
        $currentDir = dirname(__DIR__, 4); // Go up from lib/wp-routes/src/Traits
        if (strpos($currentDir, "/wp-content/plugins/") !== false) {
            return $currentDir;
        }
        
        return null;
    }

    /**
     * Render admin tabs
     */
    protected function renderAdminTabs(array $tabs): void
    {
        $current_tab = $_GET['tab'] ?? array_key_first($tabs);
        
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $tab_key => $tab_name) {
            $class = ($tab_key === $current_tab) ? 'nav-tab nav-tab-active' : 'nav-tab';
            $url = add_query_arg('tab', $tab_key, admin_url('admin.php?page=' . $this->endpoint));
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($tab_name) . '</a>';
        }
        echo '</nav>';
    }

    /**
     * Set admin template file
     */
    public function template(string $template): self
    {
        $this->adminSettings['template'] = $template;
        return $this;
    }

    /**
     * Set admin capability
     */
    public function capability(string $capability): self
    {
        $this->adminSettings['capability'] = $capability;
        return $this;
    }

    /**
     * Set admin icon
     */
    public function icon(string $icon): self
    {
        $this->adminSettings['icon'] = $icon;
        return $this;
    }

    /**
     * Set admin menu position
     */
    public function position(int $position): self
    {
        $this->adminSettings['position'] = $position;
        return $this;
    }

    /**
     * Set admin parent menu
     */
    public function parent(string $parent): self
    {
        $this->adminSettings['parent'] = $parent;
        return $this;
    }

    /**
     * Set menu title (admin routes)
     */
    public function menu(string $title): self
    {
        $this->adminSettings['menu_title'] = $title;
        return $this;
    }
    
    /**
     * Normalize template name - auto-append .php extension if missing
     */
    private function normalizeTemplateName(string $template): string
    {
        // If template already has .php extension, return as-is
        if (str_ends_with(strtolower($template), '.php')) {
            return $template;
        }
        
        // Auto-append .php extension
        return $template . '.php';
    }
}