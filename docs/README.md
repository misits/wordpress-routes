# WordPress Routes Documentation

WordPress Routes is a powerful API routing system for WordPress that provides Laravel-style routing, middleware support, and automatic controller discovery.

## Table of Contents

1. [Installation](installation.md)
2. [Configuration](configuration.md)
3. [Controllers](controllers.md)
4. [Middleware](middleware.md)
5. [Routing](routing.md)
6. [CLI Commands](cli.md)
7. [Examples](examples.md)
8. [WordPress Integration](wordpress-integration.md)

## Quick Start

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

### 2. Create Your First Controller

```bash
wp wproutes make:controller ProductController --api --resource
```

### 3. Register Routes

```php
// functions.php or plugin file
add_action('rest_api_init', function() {
    // Set your API namespace
    \WordPressRoutes\Routing\RouteManager::setNamespace('myapp/v1');
    
    // Define routes
    \WordPressRoutes\Routing\RouteManager::get('products', 'ProductController@index');
    \WordPressRoutes\Routing\RouteManager::resource('products', 'ProductController');
});
```

### 4. Access Your API

```
GET /wp-json/myapp/v1/products
POST /wp-json/myapp/v1/products
GET /wp-json/myapp/v1/products/123
PUT /wp-json/myapp/v1/products/123
DELETE /wp-json/myapp/v1/products/123
```

## Features

- **🎯 Laravel-style Routing**: Familiar routing syntax with method chaining
- **🛡️ Middleware Support**: Built-in authentication, rate limiting, and custom middleware
- **🤖 Auto-discovery**: Automatic controller and middleware detection
- **📁 Mode-based**: Separate theme and plugin development modes
- **🔧 CLI Tools**: Generate controllers and middleware with WP-CLI
- **⚡ Performance**: Optimized for WordPress REST API
- **🔐 Security**: Built-in security features and best practices

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

## Requirements

- WordPress 5.0+
- PHP 8.0+
- WP-CLI (for code generation)

## Support

- [GitHub Issues](https://github.com/your-repo/wp-routes/issues)
- [Documentation](docs/)
- [Examples](examples/)

---

*WordPress Routes - Build powerful APIs with WordPress*