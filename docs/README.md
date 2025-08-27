# WordPress Routes Documentation

WordPress Routes is a unified routing system for WordPress supporting **API, Web, Admin, and AJAX routes** with Laravel-style syntax, middleware support, and flexible template system.

## Table of Contents

1. [Installation](installation.md)
2. [Configuration](configuration.md)
3. [Unified Routing System](unified-routing.md)
4. [Controllers](controllers.md)
5. [Middleware](middleware.md)
6. [Routing](routing.md) *(Legacy API-only documentation)*
7. [CLI Commands](cli.md)
8. [Examples](examples.md)
9. [Template System](#template-system)
10. [Plugin vs Theme Mode](#plugin-vs-theme-mode)

## Quick Start - Unified Route System

### 1. Include WordPress Routes

**Theme Mode:**
```php
// functions.php
define("WPROUTES_MODE", "theme");
require_once get_template_directory() . "/vendor/wordpress-routes/bootstrap.php";
```

**Plugin Mode:**
```php
// your-plugin.php
define("WPROUTES_MODE", "plugin");
require_once __DIR__ . '/vendor/wordpress-routes/bootstrap.php';
```

### 2. Auto-Scaffolding System

WordPress Routes **automatically creates** a `/routes` directory with organized route files on first run:

**Theme**: `wp-content/themes/your-theme/routes/`
**Plugin**: `wp-content/plugins/your-plugin/routes/`

The following files are auto-generated from templates with variable replacement:
- `routes/api.php` - REST API endpoints
- `routes/web.php` - Frontend pages  
- `routes/auth.php` - Authentication routes

Template variables replaced during scaffolding:
- `{{THEME_NAME}}` - Your theme/plugin name
- `{{NAMESPACE}}` - Your theme/plugin namespace

### 3. Route Examples

```php
<?php
// routes/api.php - Auto-generated
use WordPressRoutes\Routing\Route;

// API Routes with groups
Route::group(['prefix' => 'v1'], function() {
    Route::get('posts', 'PostController@index');
    Route::post('posts', 'PostController@store');
});

// routes/web.php - Auto-generated
Route::web('about', function() {
    get_header();
    echo '<h1>About Us</h1>';
    get_footer();
})->title('About');

// routes/auth.php - Auto-generated
Route::web('login', function() {
    if (is_user_logged_in()) {
        wp_redirect(home_url('/dashboard'));
        exit();
    }
    // Show login form
})->public();

// Admin Routes (can be added to any route file)
Route::admin('settings', 'App Settings', function($request) {
    return ['settings' => get_option('app_settings')];
})->template('admin-settings.php');

// AJAX Routes (can be added to any route file)
Route::ajax('save_data', function($request) {
    return ['saved' => true];
})->auth();
```

### 4. Create Templates (Optional)

**Web template** (`theme/about.php`):
```php
<?php
$company = $GLOBALS['route_data']['company'] ?? '';
get_header();
?>
<h1>About <?php echo esc_html($company); ?></h1>
<?php get_footer(); ?>
```

**Admin template** (`theme/admin-settings.php`):
```php
<?php
$data = $GLOBALS['admin_route_data'] ?? [];
?>
<div class="wrap">
    <h1>App Settings</h1>
    <p>Settings data available here!</p>
</div>
```

### 5. Access Your Routes

- **API**: `/wp-json/wp/v2/users`
- **Web**: `https://yoursite.com/about`
- **Admin**: WordPress Admin → App Settings
- **AJAX**: JavaScript with `action: 'save_data'`

## Features

- **🚀 Auto-Scaffolding**: Automatically creates `/routes` directory structure on first run
- **🌐 4 Route Types**: API, Web, Admin, and AJAX routes in one unified system
- **📄 Template Support**: Custom templates for Web and Admin routes with data binding  
- **🎯 Laravel-style Syntax**: Familiar, elegant routing with method chaining
- **📁 Route Groups**: Group routes with shared prefix, middleware, and namespace
- **🛡️ Middleware Support**: Authentication, rate limiting, validation, CORS, and custom middleware
- **📦 Plugin & Theme Modes**: Flexible template resolution for both deployment types
- **🤖 Auto-loading**: Routes are automatically discovered and loaded from `/routes` directory
- **🔧 CLI Tools**: Generate controllers and middleware with WP-CLI
- **⚡ High Performance**: Optimized architecture with simplified web route handling
- **🎨 PHP 8+ Features**: Modern PHP with union types, match expressions, and type declarations
- **🔐 Security**: Built-in security features and WordPress best practices

## Architecture

WordPress Routes follows a clean architecture pattern:

```
/vendor/wordpress-routes/    # Library code (update-safe)
├── src/                     # Core routing classes
├── templates/               # Route file templates
├── cli/                     # WP-CLI commands
└── docs/                    # Documentation

/your-theme-or-plugin/       # User code (auto-scaffolded)
├── routes/                  # Route definitions (auto-created)
│   ├── api.php             # API endpoints
│   ├── web.php             # Frontend pages
│   └── auth.php            # Authentication routes
├── controllers/             # Your controllers (optional)
└── middleware/              # Your custom middleware (optional)
```

## Template System

WordPress Routes provides powerful template support for Web and Admin routes:

### Web Routes
```php
// Note: Web routes use exact path matching (no dynamic parameters)
Route::web('portfolio', function($request) {
    return ['projects' => get_posts(['post_type' => 'project'])];
})->template('portfolio.php')->title('My Portfolio');
```

### Admin Routes  
```php
Route::admin('dashboard', 'Analytics', function($request) {
    return ['stats' => get_analytics_data()];
})->template('admin-dashboard.php')->icon('dashicons-chart-bar');
```

### Template Resolution
- **Theme mode**: Uses WordPress `locate_template()` 
- **Plugin mode**: Searches plugin directories first, falls back to theme
- **Path support**: Simple filenames, subfolder paths, absolute paths

## Plugin vs Theme Mode

Configure deployment mode for flexible template resolution:

### Theme Mode (Default)
```php
define('WPROUTES_MODE', 'theme');
// Templates: theme/template.php
// Routes: theme/routes/*.php (auto-scaffolded)
```

### Plugin Mode
```php  
define('WPROUTES_MODE', 'plugin');
// Templates: plugin/templates/template.php  
// Routes: plugin/routes/*.php (auto-scaffolded)
```

## Requirements

- WordPress 5.0+
- PHP 8.0+ *(Required for union types and modern syntax)*
- WP-CLI (for code generation)

## Support

- [GitHub Issues](https://github.com/your-repo/wp-routes/issues)
- [Documentation](docs/)
- [Examples](examples/)

---

*WordPress Routes - Build powerful APIs with WordPress*