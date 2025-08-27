# Installation

WordPress Routes can be installed in both theme and plugin environments.

## Theme Installation

### 1. Copy Library Files

Copy the `wp-routes` library to your theme:

```
/wp-content/themes/your-theme/
├── vendor/
│   └── wordpress-routes/          # Copy entire wordpress-routes library here
├── functions.php
└── ...
```

### 2. Configure Theme Mode

Add to your `functions.php`:

```php
<?php
// Configure WordPress Routes for theme development
define("WPROUTES_MODE", "theme");

// Include the library
require_once get_template_directory() . "/vendor/wordpress-routes/bootstrap.php";
```

### 3. Create Your Routes File

Create a `routes.php` file in your theme root:

```php
<?php
// /wp-content/themes/your-theme/routes.php
defined("ABSPATH") or exit();

use WordPressRoutes\Routing\Route;

// Your API routes
Route::get('health', function($request) {
    return ['status' => 'ok'];
});

route_resource('posts', 'PostController');
```

### 4. Directory Structure

WordPress Routes will automatically use these directories:

```
/wp-content/themes/your-theme/
├── routes.php             #  Auto-loaded routes
├── controllers/           # Your API controllers
├── middleware/           # Your custom middleware
└── api/
    ├── routes.php        # Alternative routes location
    ├── controllers/      # Alternative controller location
    └── middleware/       # Alternative middleware location
```

**That's it!** Your routes are automatically loaded. No manual configuration required.

## Plugin Installation

### 1. Copy Library Files

Copy the `wordpress-routes` library to your plugin:

```
/wp-content/plugins/your-plugin/
├── vendor/
│   └── wordpress-routes/          # Copy entire wordpress-routes library here
├── your-plugin.php
└── ...
```

### 2. Configure Plugin Mode

Add to your main plugin file:

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: Plugin using WordPress Routes
 * Version: 1.0.0
 */

// Prevent direct access
defined('ABSPATH') or exit;

// Configure WordPress Routes for plugin development
define("WPROUTES_MODE", "plugin");

// Include the library
require_once __DIR__ . '/vendor/wordpress-routes/bootstrap.php';
```

### 3. Directory Structure

WordPress Routes will automatically create and use these directories:

```
/wp-content/plugins/your-plugin/
├── src/
│   ├── Controllers/       # Your API controllers (primary)
│   └── Middleware/        # Your custom middleware (primary)
├── controllers/           # Alternative controller location
├── middleware/            # Alternative middleware location
└── ...
```

## Manual Installation

If you prefer manual setup, you can specify custom paths:

```php
// Custom controller paths
wroutes_add_controller_path('/custom/path/to/controllers', 'MyApp\\Controllers');

// Or use constants
define('WPROUTES_CONTROLLER_PATHS', [
    '/custom/path/controllers',
    '/another/path/controllers'
]);
```

## Verify Installation

Check if WordPress Routes is loaded:

```php
if (function_exists('wproutes_is_loaded') && wproutes_is_loaded()) {
    echo "WordPress Routes version: " . wproutes_version();
} else {
    echo "WordPress Routes not loaded";
}
```

## CLI Installation Check

Use WP-CLI to verify the installation:

```bash
# Check if CLI commands are available
wp routes:help

# List available commands
wp help borps

# Check version
wp eval "echo wproutes_version();"
```

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 8.0 or higher
- **WP-CLI**: Latest version (for code generation)
- **REST API**: Enabled (default in WordPress)

## Troubleshooting

### Commands Not Found

If `wp routes` commands are not found:

1. Ensure WordPress Routes is properly loaded
2. Check that WP-CLI can access your WordPress installation
3. Verify the bootstrap file is included correctly

### Permission Issues

If you get permission errors:

```bash
# Use --allow-root if running as root
wp routes:make-controller TestController --allow-root

# Or fix file permissions
chown -R www-data:www-data /path/to/wordpress
```

### Mode Detection Issues

If paths are not detected correctly:

1. Explicitly set the mode: `define('WPROUTES_MODE', 'theme');`
2. Check your theme/plugin directory structure
3. Verify WordPress functions are available

---

Next: [Configuration →](configuration.md)
