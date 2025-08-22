<?php

namespace WordPressRoutes\Routing;

use WordPressRoutes\Routing\Traits\HandlesApiRoutes;
use WordPressRoutes\Routing\Traits\HandlesWebRoutes;
use WordPressRoutes\Routing\Traits\HandlesAdminRoutes;
use WordPressRoutes\Routing\Traits\HandlesAjaxRoutes;

defined("ABSPATH") or exit();

/**
 * Universal Route Handler
 *
 * Handles all types of WordPress routes: API, Web, Admin, and AJAX
 * Provides a unified Laravel-style routing interface using traits
 *
 * @since 1.0.0
 */
class Route
{
    use HandlesApiRoutes, HandlesWebRoutes, HandlesAdminRoutes, HandlesAjaxRoutes;

    /**
     * Route type constants
     */
    const TYPE_API = 'api';
    const TYPE_WEB = 'web';
    const TYPE_ADMIN = 'admin';
    const TYPE_AJAX = 'ajax';

    /**
     * Route type
     *
     * @var string
     */
    protected $type = self::TYPE_API;

    /**
     * HTTP methods
     *
     * @var array
     */
    protected $methods = [];

    /**
     * Route path/endpoint
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Route namespace (for API routes)
     *
     * @var string
     */
    protected $namespace;

    /**
     * Route callback
     *
     * @var callable|string
     */
    protected $callback;

    /**
     * Route middleware
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Route name
     *
     * @var string
     */
    protected $name;

    /**
     * Route parameters
     *
     * @var array
     */
    protected $params = [];

    /**
     * Route attributes
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Admin page settings (for admin routes)
     *
     * @var array
     */
    protected $adminSettings = [
        'capability' => 'manage_options',
        'icon' => 'dashicons-admin-generic',
        'position' => null,
        'parent' => null,
        'menu_title' => null,
        'page_title' => null,
        'template' => null
    ];

    /**
     * Web route settings
     *
     * @var array
     */
    protected $webSettings = [
        'template' => null,
        'title' => null,
        'priority' => 'top'
    ];

    /**
     * Create new route
     *
     * @param string|array $methods
     * @param string $endpoint
     * @param callable|string $callback
     * @param string $type
     */
    public function __construct($methods, $endpoint, $callback, $type = self::TYPE_API)
    {
        $this->methods = is_array($methods) ? $methods : [$methods];
        $this->endpoint = $this->normalizeEndpoint($endpoint);
        $this->callback = $callback;
        $this->type = $type;
        
        // Don't set namespace here - defer until registration to get current value
        
        $this->parseParameters();
        
        // Auto-register immediately - RouteManager will handle the WordPress hooks
        $this->register();
    }

    /**
     * Create API route
     *
     * @param string|array $methods
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function api($methods, $endpoint, $callback)
    {
        return new static($methods, $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create GET API route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function get($endpoint, $callback)
    {
        return new static('GET', $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create POST API route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function post($endpoint, $callback)
    {
        return new static('POST', $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create PUT API route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function put($endpoint, $callback)
    {
        return new static('PUT', $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create DELETE API route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function delete($endpoint, $callback)
    {
        return new static('DELETE', $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create PATCH API route
     *
     * @param string $endpoint
     * @param callable|string $callback
     * @return static
     */
    public static function patch($endpoint, $callback)
    {
        return new static('PATCH', $endpoint, $callback, self::TYPE_API);
    }

    /**
     * Create web route
     *
     * @param string $path
     * @param callable|string $callback
     * @return static
     */
    public static function web($path, $callback)
    {
        return new static(['GET'], $path, $callback, self::TYPE_WEB);
    }

    /**
     * Create admin route
     *
     * @param string $slug
     * @param string $title
     * @param callable|string $callback
     * @return static
     */
    public static function admin($slug, $title, $callback)
    {
        $route = new static(['GET', 'POST'], $slug, $callback, self::TYPE_ADMIN);
        $route->adminSettings['page_title'] = $title;
        $route->adminSettings['menu_title'] = $title;
        return $route;
    }

    /**
     * Create AJAX route
     *
     * @param string $action
     * @param callable $callback
     * @param bool $nopriv
     * @return static
     */
    public static function ajax($action, $callback, $nopriv = false)
    {
        $route = new static(['POST'], $action, $callback, self::TYPE_AJAX);
        $route->attributes['nopriv'] = $nopriv;
        return $route;
    }

    /**
     * Create a route group
     *
     * @param array $attributes
     * @param callable $callback
     * @return void
     */
    public static function group(array $attributes, callable $callback)
    {
        RouteManager::group($attributes, $callback);
    }

    /**
     * Create a resource route
     *
     * @param string $name
     * @param string $controller
     * @param array $options
     * @return void
     */
    public static function resource($name, $controller, array $options = [])
    {
        RouteManager::resource($name, $controller, $options);
    }

    /**
     * Normalize endpoint
     *
     * @param string $endpoint
     * @return string
     */
    protected function normalizeEndpoint($endpoint)
    {
        return trim($endpoint, '/');
    }

    /**
     * Parse route parameters
     */
    protected function parseParameters()
    {
        preg_match_all('/{([^}]+)}/', $this->endpoint, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $param) {
                $paramName = str_replace('?', '', $param);
                $this->params[$paramName] = $param;
            }
        }
    }

    /**
     * Set route middleware
     *
     * @param string|array|callable $middleware
     * @return self
     */
    public function middleware($middleware)
    {
        if (is_string($middleware)) {
            $this->middleware[] = $middleware;
        } elseif (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } elseif (is_callable($middleware)) {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    /**
     * Set route name
     *
     * @param string $name
     * @return self
     */
    public function name($name)
    {
        $this->name = $name;
        RouteManager::addNamedRoute($name, $this);
        return $this;
    }

    /**
     * Set route namespace
     *
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Set route attributes
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function attribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Whether the route has been registered
     *
     * @var bool
     */
    protected $registered = false;

    /**
     * Register the route
     *
     * @return self
     */
    public function register()
    {
        // Prevent double registration
        if ($this->registered) {
            return $this;
        }
        
        // Set namespace for API routes at registration time to get current value
        if ($this->type === self::TYPE_API && !$this->namespace) {
            $this->namespace = RouteManager::getNamespace();
        }
        
        switch ($this->type) {
            case self::TYPE_API:
                $this->registerApiRoute();
                break;
            case self::TYPE_WEB:
                $this->registerWebRoute();
                break;
            case self::TYPE_ADMIN:
                $this->registerAdminRoute();
                break;
            case self::TYPE_AJAX:
                $this->registerAjaxRoute();
                break;
        }
        
        // Add to route collection
        RouteManager::addRouteInstance($this);
        
        $this->registered = true;
        
        return $this;
    }

    /**
     * Set additional capability check
     * Works differently based on route type
     *
     * @param string $capability
     * @return self
     */
    public function can($capability)
    {
        if ($this->type === self::TYPE_ADMIN) {
            // For admin routes, set the page capability
            $this->adminSettings['capability'] = $capability;
        } else {
            // For other routes, add as middleware
            $this->middleware('capability:' . $capability);
        }
        return $this;
    }

    /**
     * Set route as public (no auth required)
     *
     * @return self
     */
    public function public()
    {
        // Remove auth middleware if exists
        $this->middleware = array_filter($this->middleware, function($m) {
            return $m !== 'auth';
        });
        
        // For AJAX routes, allow nopriv
        if ($this->type === self::TYPE_AJAX) {
            $this->nopriv(true);
        }
        
        return $this;
    }

    /**
     * Set route as private (auth required)
     *
     * @return self
     */
    public function private()
    {
        // Add auth middleware if not exists
        if (!in_array('auth', $this->middleware)) {
            array_unshift($this->middleware, 'auth');
        }
        
        // For AJAX routes, disable nopriv
        if ($this->type === self::TYPE_AJAX) {
            $this->nopriv(false);
        }
        
        return $this;
    }

    /**
     * Add CORS support (mainly for API routes)
     *
     * @param array|string $origins
     * @return self
     */
    public function cors($origins = '*')
    {
        if ($origins === '*') {
            $this->middleware('cors');
        } else {
            $this->middleware(function($request) use ($origins) {
                $allowedOrigins = is_array($origins) ? $origins : [$origins];
                $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
                
                if (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins)) {
                    header('Access-Control-Allow-Origin: ' . $origin);
                    header('Access-Control-Allow-Methods: ' . implode(', ', $this->methods));
                    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
                    header('Access-Control-Allow-Credentials: true');
                }
                
                if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    exit(0);
                }
                
                return null;
            });
        }
        
        return $this;
    }

    /**
     * Quick validation setup
     *
     * @param array $rules
     * @param array $messages
     * @return self
     */
    public function validate(array $rules, array $messages = [])
    {
        $this->middleware(\WordPressRoutes\Routing\Middleware\ValidationMiddleware::rules($rules, $messages));
        return $this;
    }

    /**
     * Rate limit the route
     *
     * @param int $requests
     * @param int $minutes
     * @return self
     */
    public function rateLimit($requests = 60, $minutes = 1)
    {
        $this->middleware(['rate_limit', $requests, $minutes]);
        return $this;
    }

    // Getters for route information
    
    public function getType() { return $this->type; }
    public function getMethods() { return $this->methods; }
    public function getEndpoint() { return $this->endpoint; }
    public function getNamespace() { return $this->namespace; }
    public function getName() { return $this->name; }
    public function getMiddleware() { return $this->middleware; }
    public function getCallback() { return $this->callback; }
    public function getParams() { return $this->params; }
    public function getAttributes() { return $this->attributes; }
    
    /**
     * Get callback description for CLI display
     */
    public function getCallbackDescription()
    {
        if (is_string($this->callback)) {
            return $this->callback;
        }
        return 'Custom Handler';
    }
    
    /**
     * Convert route to array representation
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'type' => $this->type,
            'methods' => $this->methods,
            'endpoint' => $this->endpoint,
            'namespace' => $this->namespace,
            'name' => $this->name,
            'middleware' => array_map(function($m) {
                return is_callable($m) ? 'callable' : $m;
            }, $this->middleware),
            'params' => array_keys($this->params),
            'attributes' => $this->attributes
        ];
    }

    // Add trait-specific method implementations to avoid conflicts

    /**
     * Set page template (web routes) - Implementation in trait
     * @param string $template
     * @return self
     */
    public function template($template)
    {
        if ($this->type === self::TYPE_WEB) {
            $this->webSettings['template'] = $template;
        }
        return $this;
    }

    /**
     * Set page title - Implementation varies by route type
     * @param string|callable $title
     * @return self
     */
    public function title($title)
    {
        if ($this->type === self::TYPE_WEB) {
            $this->webSettings['title'] = $title;
        } elseif ($this->type === self::TYPE_ADMIN) {
            $this->adminSettings['page_title'] = $title;
        }
        return $this;
    }

    /**
     * Set route priority (web routes)
     * @param string $priority
     * @return self
     */
    public function priority($priority = 'top')
    {
        if ($this->type === self::TYPE_WEB) {
            $this->webSettings['priority'] = $priority;
        }
        return $this;
    }

    /**
     * Set admin icon
     * @param string $icon
     * @return self
     */
    public function icon($icon)
    {
        if ($this->type === self::TYPE_ADMIN) {
            $this->adminSettings['icon'] = $icon;
        }
        return $this;
    }

    /**
     * Set admin menu position
     * @param int $position
     * @return self
     */
    public function position($position)
    {
        if ($this->type === self::TYPE_ADMIN) {
            $this->adminSettings['position'] = $position;
        }
        return $this;
    }

    /**
     * Set admin parent menu
     * @param string $parent
     * @return self
     */
    public function parent($parent)
    {
        if ($this->type === self::TYPE_ADMIN) {
            $this->adminSettings['parent'] = $parent;
        }
        return $this;
    }

    /**
     * Set menu title (admin routes)
     * @param string $title
     * @return self
     */
    public function menu($title)
    {
        if ($this->type === self::TYPE_ADMIN) {
            $this->adminSettings['menu_title'] = $title;
        }
        return $this;
    }

    /**
     * Allow non-privileged access (AJAX routes)
     * @param bool $allow
     * @return self
     */
    public function nopriv($allow = true)
    {
        if ($this->type === self::TYPE_AJAX) {
            $this->attributes['nopriv'] = $allow;
        }
        return $this;
    }
}