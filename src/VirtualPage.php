<?php

namespace WordPressRoutes\Routing;

use stdClass;
use WP_Post;

defined("ABSPATH") or exit();

/**
 * Virtual Page Creator - Simplified approach based on wordpress-router
 * 
 * Creates virtual WordPress pages for web routes with PHP 8+ features
 *
 * @since 1.0.0
 */
class VirtualPage
{
    private string $uri;
    private string $title;
    private ?string $template;
    private mixed $callback = null;
    private ?WP_Post $wpPost = null;

    public function __construct(?string $template = null, string $title = '', string $uri = '')
    {
        $this->template = $template;
        $this->title = $title;
        $this->uri = $uri;
    }

    /**
     * Set the URI for this virtual page
     */
    public function setUri(string $uri): self
    {
        $this->uri = $uri;
        return $this;
    }

    /**
     * Set the title for this virtual page
     */
    public function setTitle(string $title): self
    {
        $this->title = filter_var($title, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return $this;
    }

    /**
     * Set the template for this virtual page
     */
    public function setTemplate(?string $template): self
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Set callback for data generation
     */
    public function setCallback(callable $callback): self
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * Process the virtual page
     */
    public function process(): void
    {
        add_action('template_redirect', [$this, 'createPage'], 1);
    }

    /**
     * Create the virtual page and set up WordPress globals
     */
    public function createPage(): void
    {
        global $wp, $wp_query;

        // Execute callback to get data if provided
        $response = null;
        if (is_callable($this->callback)) {
            $response = call_user_func($this->callback);
        }

        // Store response data globally
        if (is_array($response)) {
            $GLOBALS['route_data'] = $response;
        }

        // Create virtual post
        $this->createPostInstance();

        // Update WordPress globals to treat this as a real page
        $this->updateWordPressGlobals($wp_query, $wp);

        // Set up custom template if provided
        $this->setupCustomTemplate();

        // Set 200 status header
        status_header(200);
    }

    /**
     * Create virtual WP_Post instance
     */
    private function createPostInstance(): void
    {
        if ($this->wpPost !== null) {
            return;
        }

        $post = new stdClass();
        $post->ID = -99;
        $post->ancestors = [];
        $post->comment_status = 'closed';
        $post->comment_count = 0;
        $post->filter = 'raw';
        $post->guid = home_url($this->uri);
        $post->is_virtual = true;
        $post->menu_order = 0;
        $post->pinged = '';
        $post->ping_status = 'closed';
        $post->post_title = $this->title ?: ucfirst($this->uri);
        $post->post_name = sanitize_title($this->template ?: $this->uri);
        $post->post_excerpt = '';
        $post->post_parent = 0;
        $post->post_type = 'page';
        $post->post_status = 'publish';
        $post->post_date = current_time('mysql');
        $post->post_date_gmt = current_time('mysql', 1);
        $post->modified = $post->post_date;
        $post->modified_gmt = $post->post_date_gmt;
        $post->post_password = '';
        $post->post_content_filtered = '';
        $post->post_author = is_user_logged_in() ? get_current_user_id() : 0;
        $post->post_content = '';
        $post->post_mime_type = '';
        $post->to_ping = '';

        $this->wpPost = new WP_Post($post);
    }

    /**
     * Update WordPress globals to make this appear as a real page
     */
    private function updateWordPressGlobals(\WP_Query $wp_query, \WP $wp): void
    {
        // Update the main query
        $wp_query->current_post = $this->wpPost->ID;
        $wp_query->found_posts = 1;
        $wp_query->is_page = true; // Critical!
        $wp_query->is_singular = true; // Critical!
        $wp_query->is_single = false;
        $wp_query->is_attachment = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        $wp_query->is_tag = false;
        $wp_query->is_tax = false;
        $wp_query->is_author = false;
        $wp_query->is_date = false;
        $wp_query->is_year = false;
        $wp_query->is_month = false;
        $wp_query->is_day = false;
        $wp_query->is_time = false;
        $wp_query->is_search = false;
        $wp_query->is_feed = false;
        $wp_query->is_comment_feed = false;
        $wp_query->is_trackback = false;
        $wp_query->is_home = false;
        $wp_query->is_embed = false;
        $wp_query->is_404 = false; // Critical!
        $wp_query->is_paged = false;
        $wp_query->is_admin = false;
        $wp_query->is_preview = false;
        $wp_query->is_robots = false;
        $wp_query->is_posts_page = false;
        $wp_query->is_post_type_archive = false;
        $wp_query->max_num_pages = 1;
        $wp_query->post = $this->wpPost;
        $wp_query->posts = [$this->wpPost];
        $wp_query->post_count = 1;
        $wp_query->queried_object = $this->wpPost;
        $wp_query->queried_object_id = $this->wpPost->ID;
        $wp_query->query_vars['error'] = '';
        unset($wp_query->query['error']);

        $GLOBALS['wp_query'] = $wp_query;

        $wp->query = [];
        $wp->register_globals();
        wp_cache_add(-99, $this->wpPost, 'posts');
    }

    /**
     * Set up custom template if provided
     */
    private function setupCustomTemplate(): void
    {
        if (!$this->template) {
            return;
        }

        add_filter('template_include', function(string $template): string {
            $customTemplate = $this->findTemplate($this->template);
            return $customTemplate ?: $template;
        }, 99);
    }
    
    /**
     * Find template file - supports both theme and plugin modes
     */
    private function findTemplate(string $template): ?string
    {
        // If it's an absolute path, use it directly
        if (str_starts_with($template, '/') && file_exists($template)) {
            return $template;
        }
        
        $mode = defined("WPROUTES_MODE")
            ? WPROUTES_MODE
            : (defined("WPORM_MODE")
                ? WPORM_MODE
                : "theme");
                
        if ($mode === "plugin") {
            return $this->findPluginTemplate($template);
        }
        
        // Theme mode (default)
        return $this->findThemeTemplate($template);
    }
    
    /**
     * Find template in plugin mode
     */
    private function findPluginTemplate(string $template): ?string
    {
        // Get plugin directory
        $pluginDir = $this->getPluginDirectory();
        
        if (!$pluginDir) {
            // Fallback to theme mode if plugin dir not found
            return $this->findThemeTemplate($template);
        }
        
        $templatePaths = [];
        
        // If template contains path, use it directly from plugin root
        if (str_contains($template, '/')) {
            $templatePaths[] = $pluginDir . $template;
            $templatePaths[] = $pluginDir . 'templates/' . $template;
        } else {
            // Search in plugin template directories
            $templatePaths = [
                $pluginDir . 'templates/' . $template,
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
        return $this->findThemeTemplate($template);
    }
    
    /**
     * Find template in theme mode
     */
    private function findThemeTemplate(string $template): ?string
    {
        // If template contains a path, use it directly from theme root
        if (str_contains($template, '/')) {
            $themeTemplate = get_template_directory() . '/' . $template;
            if (file_exists($themeTemplate)) {
                return $themeTemplate;
            }
            
            // Child theme check
            if (get_template_directory() !== get_stylesheet_directory()) {
                $childTemplate = get_stylesheet_directory() . '/' . $template;
                if (file_exists($childTemplate)) {
                    return $childTemplate;
                }
            }
        }
        
        // Use WordPress's locate_template for simple filenames
        $templatePath = locate_template($template);
        if ($templatePath) {
            return $templatePath;
        }
        
        // Final fallback: check theme root
        $themeTemplate = get_template_directory() . '/' . $template;
        if (file_exists($themeTemplate)) {
            return $themeTemplate;
        }
        
        return null;
    }
    
    /**
     * Get plugin directory
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
        $currentDir = dirname(__DIR__, 3); // Go up from lib/wp-routes/src
        if (strpos($currentDir, "/wp-content/plugins/") !== false) {
            return $currentDir;
        }
        
        return null;
    }
}