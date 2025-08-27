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

### 3. Auto-Scaffolding (Automatic)

WordPress Routes **automatically creates** your route files on first run. No manual setup needed!

On first initialization, these files will be auto-generated:

```php
// routes/api.php - Auto-created
use WordPressRoutes\Routing\Route;

// API Routes with groups
Route::group(['prefix' => 'v1'], function() {
    Route::get('posts', 'PostController@index');
});

// routes/web.php - Auto-created
Route::web('about', function() {
    get_header();
    echo '<h1>About Your Theme</h1>';
    get_footer();
})->title('About Us');

// routes/auth.php - Auto-created
Route::web('login', function() {
    if (is_user_logged_in()) {
        wp_redirect(home_url('/dashboard'));
        exit();
    }
    // Login form logic
})->public();
```

### 4. Directory Structure (Auto-Created)

WordPress Routes automatically creates and uses these directories:

```
/wp-content/themes/your-theme/
├── routes/               # Auto-created route directory
│   ├── api.php          # REST API endpoints (auto-scaffolded)
│   ├── web.php          # Frontend pages (auto-scaffolded)
│   └── auth.php         # Authentication routes (auto-scaffolded)
├── controllers/         # Your API controllers (optional)
├── middleware/          # Your custom middleware (optional)
└── vendor/
    └── wordpress-routes/ # Library files
```

**That's it!** Your route files are automatically scaffolded and loaded. No manual configuration required.

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

### 3. Directory Structure (Auto-Created)

WordPress Routes automatically creates and uses these directories:

```
/wp-content/plugins/your-plugin/
├── routes/               # Auto-created route directory
│   ├── api.php          # REST API endpoints (auto-scaffolded)
│   ├── web.php          # Frontend pages (auto-scaffolded)
│   └── auth.php         # Authentication routes (auto-scaffolded)
├── controllers/         # Your API controllers (optional)
├── middleware/          # Your custom middleware (optional)
└── vendor/
    └── wordpress-routes/ # Library files
```

## Custom Configuration

The auto-scaffolding system can be customized:

```php
// Disable auto-scaffolding (routes directory must exist)
define('WPROUTES_AUTO_SCAFFOLD', false);

// Custom controller paths (optional)
wroutes_add_controller_path('/custom/path/to/controllers', 'MyApp\\Controllers');

// Custom route file locations (if not using auto-scaffolding)
define('WPROUTES_CUSTOM_ROUTES', [
    '/custom/path/routes.php',
    '/another/path/api-routes.php'
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
