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
require_once get_template_directory() . "/lib/wp-routes/bootstrap.php";
```

**Plugin Mode:**
```php
// your-plugin.php
define("WPROUTES_MODE", "plugin");
require_once __DIR__ . '/lib/wp-routes/bootstrap.php';
```

### 2. Create routes.php (Auto-loaded!)

**Theme**: Create `theme/routes.php`
**Plugin**: Create `plugin/routes.php`

```php
<?php
use WordPressRoutes\Routing\Route;

// API Routes (REST API endpoints)
Route::get('users', function($request) {
    return get_users();
});

// Web Routes (frontend pages with templates)
Route::web('about', function($request) {
    return ['company' => 'My Company'];
})->template('about.php');

// Admin Routes (dashboard pages)
Route::admin('settings', 'App Settings', function($request) {
    return ['settings' => get_option('app_settings')];
})->template('admin-settings.php');

// AJAX Routes (WordPress AJAX handlers)
Route::ajax('save_data', function($request) {
    return ['saved' => true];
})->auth();
```

### 3. Create Templates (Optional)

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

### 4. Access Your Routes

- **API**: `/wp-json/wp/v2/users`
- **Web**: `https://yoursite.com/about`
- **Admin**: WordPress Admin → App Settings
- **AJAX**: JavaScript with `action: 'save_data'`

## Features

- **🌐 4 Route Types**: API, Web, Admin, and AJAX routes in one unified system
- **📄 Template Support**: Custom templates for Web and Admin routes with data binding  
- **🎯 Laravel-style Syntax**: Familiar, elegant routing with method chaining
- **🛡️ Middleware Support**: Authentication, rate limiting, validation, CORS, and custom middleware
- **📦 Plugin & Theme Modes**: Flexible template resolution for both deployment types
- **🤖 Auto-loading**: Routes are automatically discovered and loaded
- **🔧 CLI Tools**: Generate controllers and middleware with WP-CLI
- **⚡ High Performance**: Optimized architecture with simplified web route handling
- **🎨 PHP 8+ Features**: Modern PHP with union types, match expressions, and type declarations
- **🔐 Security**: Built-in security features and WordPress best practices

## Architecture

WordPress Routes follows a clean architecture pattern:

```
/lib/wp-routes/          # Library code (update-safe)
├── src/                 # Core routing classes
├── cli/                 # WP-CLI commands
└── docs/                # Documentation

/your-app/               # User code (never touched by updates)
├── controllers/         # Your API controllers
├── middleware/          # Your custom middleware
└── routes/              # Route definitions (optional)
```

## Template System

WordPress Routes provides powerful template support for Web and Admin routes:

### Web Routes
```php
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
// Routes: theme/routes.php
```

### Plugin Mode
```php  
define('WPROUTES_MODE', 'plugin');
// Templates: plugin/templates/template.php  
// Routes: plugin/routes.php
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