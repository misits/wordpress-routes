# Configuration

WordPress Routes provides flexible configuration options for different development environments.

##  Automatic Route Loading 

**Zero Configuration Required!** WordPress Routes now automatically detects and loads your routes based on `WPROUTES_MODE`.

### Quick Setup

1. **Set your mode** (optional - defaults to "theme"):
```php
define('WPROUTES_MODE', 'theme'); // or 'plugin'
```

2. **Create routes file** in the right location:
```php
// Theme: /wp-content/themes/your-theme/routes.php
// Plugin: /wp-content/plugins/your-plugin/routes.php
<?php
route_resource('posts', 'PostController');
Route::get('health', function($request) {
    return ['status' => 'ok'];
});
```

3. **That's it!** Routes are automatically loaded on `rest_api_init` and in CLI contexts.

### Auto-Detection Paths

**Theme Mode:** Searches in order:
- `{child-theme}/routes.php` 
- `{child-theme}/routes/api.php`
- `{child-theme}/api/routes.php`
- `{parent-theme}/routes.php` 
- `{parent-theme}/routes/api.php`
- `{parent-theme}/api/routes.php`

**Plugin Mode:** Searches in order:
- `{plugin-root}/routes.php` 
- `{plugin-root}/routes/api.php`
- `{plugin-root}/src/routes.php`

### Disable Auto-Loading

```php
// Before including bootstrap.php
define('WPROUTES_NO_AUTO_ROUTES', true);
require_once get_template_directory() . '/lib/wp-routes/bootstrap.php';

// Then manually load routes
require_once get_template_directory() . '/my-custom-routes.php';
```

## Mode Configuration

### Theme Mode

For theme development, set theme mode in `functions.php`:

```php
<?php
// Configure for theme development
define("WPROUTES_MODE", "theme");

// Optional: Also configure WP-ORM mode (they work together)
define("WPORM_MODE", "theme");

// Include WordPress Routes
require_once get_template_directory() . "/lib/wp-routes/bootstrap.php";
```

**Directory Structure (Theme Mode):**
```
/wp-content/themes/your-theme/
├── controllers/           # Primary controller location
├── middleware/           # Primary middleware location
└── api/
    ├── controllers/      # Alternative controller location
    └── middleware/       # Alternative middleware location
```

### Plugin Mode

For plugin development, set plugin mode in your main plugin file:

```php
<?php
/**
 * Plugin Name: My Plugin
 */

// Configure for plugin development
define("WPROUTES_MODE", "plugin");

// Include WordPress Routes
require_once __DIR__ . '/lib/wp-routes/bootstrap.php';
```

**Directory Structure (Plugin Mode):**
```
/wp-content/plugins/your-plugin/
├── src/
│   ├── Controllers/      # Primary controller location
│   └── Middleware/       # Primary middleware location
├── controllers/          # Alternative controller location
└── middleware/           # Alternative middleware location
```

## Custom Paths

### Manual Path Registration

Register custom controller paths:

```php
// Add custom controller paths
wroutes_add_controller_path('/custom/path/controllers', 'MyApp\\Controllers');
wroutes_add_controller_path('/another/path', 'AnotherApp\\Controllers');

// Add paths without namespaces
wroutes_add_controller_path('/simple/path');
```

### Using Constants

Define custom paths using constants:

```php
// Before including bootstrap.php
define('WPROUTES_CONTROLLER_PATHS', [
    get_template_directory() . '/api/controllers',
    get_template_directory() . '/custom/controllers',
    '/absolute/path/to/controllers'
]);

define('WPROUTES_MIDDLEWARE_PATHS', [
    get_template_directory() . '/api/middleware',
    get_template_directory() . '/custom/middleware'
]);
```

## Auto-initialization

### Disable Auto-initialization

Prevent automatic initialization:

```php
// Before including bootstrap.php
define('WPROUTES_NO_AUTO_INIT', true);

// Include bootstrap
require_once get_template_directory() . "/lib/wp-routes/bootstrap.php";

// Manually initialize when needed
add_action('rest_api_init', function() {
    wproutes_boot();
});
```

### Custom Initialization Timing

Control when WordPress Routes initializes:

```php
// Initialize early (priority 5 - default)
add_action('init', 'wproutes_boot', 5);

// Initialize later (priority 20)
add_action('init', 'wproutes_boot', 20);

// Initialize only for REST API requests
add_action('rest_api_init', 'wproutes_boot');
```

## API Namespace Configuration

### Global Namespace

Set a global namespace for all your routes:

```php
add_action('rest_api_init', function() {
    // Set global namespace
    \WordPressRoutes\Routing\Route::setNamespace('myapp/v1');
    
    // All routes will use this namespace
    \WordPressRoutes\Routing\Route::get('products', 'ProductController@index');
    // Creates: /wp-json/myapp/v1/products
});
```

### Route-specific Namespaces

Use different namespaces for different route groups:

```php
add_action('rest_api_init', function() {
    // Public API
    \WordPressRoutes\Routing\Route::setNamespace('api/v1');
    \WordPressRoutes\Routing\Route::get('products', 'ProductController@index');
    
    // Admin API
    \WordPressRoutes\Routing\Route::setNamespace('admin/v1');
    \WordPressRoutes\Routing\Route::get('users', 'AdminController@users');
});
```

## Environment-specific Configuration

### Development Configuration

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Enable debug features in development
    define('WPROUTES_DEBUG', true);
    
    // More verbose error messages
    define('WPROUTES_DEBUG_VERBOSE', true);
}
```

### Production Configuration

```php
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    // Disable debug features in production
    define('WPROUTES_DEBUG', false);
    
    // Enable caching
    define('WPROUTES_CACHE_ENABLED', true);
}
```

## Advanced Configuration

### Custom Controller Resolution

```php
// Custom controller namespace resolution
add_filter('wproutes_controller_namespace', function($namespace, $controller) {
    // Custom logic to determine namespace
    if (strpos($controller, 'Admin') === 0) {
        return 'MyApp\\Admin\\Controllers';
    }
    return 'MyApp\\Api\\Controllers';
}, 10, 2);
```

### Custom Middleware Resolution

```php
// Custom middleware resolution
add_filter('wproutes_middleware_namespace', function($namespace, $middleware) {
    if (strpos($middleware, 'Auth') !== false) {
        return 'MyApp\\Auth\\Middleware';
    }
    return 'MyApp\\Middleware';
}, 10, 2);
```

## Configuration Validation

### Check Configuration

```php
// Check if WordPress Routes is properly configured
function check_wproutes_config() {
    if (!function_exists('wproutes_is_loaded')) {
        return 'WordPress Routes not loaded';
    }
    
    if (!wproutes_is_loaded()) {
        return 'WordPress Routes not initialized';
    }
    
    $paths = wproutes_get_controller_paths();
    if (empty($paths)) {
        return 'No controller paths configured';
    }
    
    return 'Configuration OK';
}

// Use in admin or debug
echo check_wproutes_config();
```

### Debug Configuration

```php
// Display current configuration
function debug_wproutes_config() {
    if (!function_exists('wproutes_is_loaded')) {
        return;
    }
    
    echo "WordPress Routes Version: " . wproutes_version() . "\n";
    echo "Mode: " . (defined('WPROUTES_MODE') ? WPROUTES_MODE : 'auto') . "\n";
    echo "Controller Paths:\n";
    
    foreach (wproutes_get_controller_paths() as $path) {
        echo "  - {$path}\n";
    }
}

// Call in WP-CLI or debug context
if (defined('WP_CLI') && WP_CLI) {
    debug_wproutes_config();
}
```

## Configuration Best Practices

1. **Set Mode Explicitly**: Always define `WPROUTES_MODE` for consistency
2. **Use Constants**: Define paths with constants before bootstrap inclusion  
3. **Environment-specific**: Use different configs for dev/staging/production
4. **Namespace Consistently**: Use consistent API namespace patterns
5. **Validate Config**: Check configuration in development environments

---

Next: [Controllers →](controllers.md)