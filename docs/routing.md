# Routing

WordPress Routes provides a powerful, Laravel-inspired routing system that integrates seamlessly with WordPress REST API.

## ⭐ Modern Routes File Structure

The modern way to organize routes is using a dedicated `routes.php` file that's automatically loaded.

### Creating Your Routes File

Create a `routes.php` file in your theme or plugin root:

```php
<?php
/**
 * API Routes Definition
 * 
 * Define all your API routes here using Laravel-style syntax
 * This file is automatically loaded by wp-routes based on WPROUTES_MODE
 */

defined("ABSPATH") or exit();

use WordPressRoutes\Routing\RouteManager;

// Set API namespace for all routes
RouteManager::setNamespace('wp/v2');

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| These routes are accessible without authentication
*/

// Simple endpoint
RouteManager::get('health', function($request) {
    return ['status' => 'ok', 'timestamp' => current_time('mysql')];
});

// Resource routes (CRUD)
route_resource('posts', 'PostController', [
    'only' => ['index', 'show'] // Public read-only
]);

// Search endpoint
RouteManager::get('search', function($request) {
    $query = $request->query('q', '');
    
    if (empty($query)) {
        return new WP_Error('missing_query', 'Search query required', ['status' => 400]);
    }
    
    // Search implementation
    $posts = get_posts([
        's' => sanitize_text_field($query),
        'post_type' => 'any',
        'post_status' => 'publish',
        'numberposts' => 10
    ]);
    
    return ['query' => $query, 'results' => count($posts)];
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes  
|--------------------------------------------------------------------------
| These routes require user authentication
*/

RouteManager::group(['middleware' => 'auth'], function() {
    // User's own posts - full CRUD
    route_resource('my/posts', 'PostController', [
        'only' => ['store', 'update', 'destroy']
    ]);
    
    // Profile management
    RouteManager::get('profile', 'UserController@profile');
    RouteManager::put('profile', 'UserController@updateProfile');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| These routes require admin privileges
*/

RouteManager::group([
    'middleware' => ['auth', 'capability:manage_options'],
    'prefix' => 'admin'
], function() {
    // Admin-only full access
    route_resource('posts', 'PostController');
    route_resource('users', 'UserController');
    
    // System management
    RouteManager::get('stats', 'AdminController@stats');
    RouteManager::post('cache/clear', 'AdminController@clearCache');
});

/*
|--------------------------------------------------------------------------
| API Versioning Example
|--------------------------------------------------------------------------
*/

// V2 API with enhanced features
RouteManager::group(['namespace' => 'myapp/v2'], function() {
    route_resource('posts', 'V2\\PostController');
});

/*
|--------------------------------------------------------------------------
| Development Routes
|--------------------------------------------------------------------------
| Only available when WP_DEBUG is enabled
*/

if (defined('WP_DEBUG') && WP_DEBUG) {
    RouteManager::get('debug/routes', function($request) {
        $routes = RouteManager::getRoutes();
        return [
            'total_routes' => count($routes),
            'routes' => array_map(function($route) {
                return [
                    'methods' => $route->getMethods(),
                    'namespace' => $route->getNamespace(),
                    'endpoint' => $route->getEndpoint(),
                ];
            }, $routes)
        ];
    });
}
```

### File Locations

The `routes.php` file should be placed in:

**Theme Mode:**
- `{theme}/routes.php` ⭐ **Recommended**
- `{theme}/routes/api.php`
- `{theme}/api/routes.php`

**Plugin Mode:**
- `{plugin}/routes.php` ⭐ **Recommended**  
- `{plugin}/routes/api.php`
- `{plugin}/src/routes.php`

### Benefits of Routes File Approach

✅ **Laravel-style** - Familiar route organization  
✅ **Separation of concerns** - Routes separate from theme logic  
✅ **Easy maintenance** - All routes in one place  
✅ **Version control friendly** - Easy to track route changes  
✅ **Automatic loading** - Zero configuration required  
✅ **Organized structure** - Routes grouped by functionality

## Basic Routing

### Defining Routes

```php
use WordPressRoutes\Routing\RouteManager;

add_action('rest_api_init', function() {
    // Set API namespace
    RouteManager::setNamespace('myapp/v1');
    
    // Basic routes
    RouteManager::get('products', 'ProductController@index');
    RouteManager::post('products', 'ProductController@store');
    RouteManager::get('products/{id}', 'ProductController@show');
    RouteManager::put('products/{id}', 'ProductController@update');
    RouteManager::delete('products/{id}', 'ProductController@destroy');
});
```

### Available Route Methods

```php
// HTTP GET
RouteManager::get('users', 'UserController@index');

// HTTP POST
RouteManager::post('users', 'UserController@store');

// HTTP PUT
RouteManager::put('users/{id}', 'UserController@update');

// HTTP PATCH
RouteManager::patch('users/{id}', 'UserController@partialUpdate');

// HTTP DELETE
RouteManager::delete('users/{id}', 'UserController@destroy');

// Multiple HTTP methods
RouteManager::match(['GET', 'POST'], 'search', 'SearchController@handle');

// Any HTTP method
RouteManager::any('webhook', 'WebhookController@handle');
```

### Route Parameters

```php
// Required parameters
RouteManager::get('users/{id}', 'UserController@show');
RouteManager::get('posts/{post}/comments/{comment}', 'CommentController@show');

// Optional parameters
RouteManager::get('products/{category?}', 'ProductController@index');

// Parameter constraints
RouteManager::get('users/{id}', 'UserController@show')
    ->where('id', '[0-9]+'); // Only numeric IDs

RouteManager::get('posts/{slug}', 'PostController@show')
    ->where('slug', '[a-z-]+'); // Only lowercase letters and dashes
```

## Route Naming

### Named Routes

```php
// Name routes for URL generation
RouteManager::get('products', 'ProductController@index')
    ->name('products.index');

RouteManager::get('products/{id}', 'ProductController@show')
    ->name('products.show');

// Generate URLs
$url = route_url('products.index'); 
// Returns: /wp-json/myapp/v1/products

$url = route_url('products.show', ['id' => 123]);
// Returns: /wp-json/myapp/v1/products/123
```

## Resource Routes

### Basic Resource

```php
// Creates all CRUD routes
RouteManager::resource('products', 'ProductController');

// Equivalent to:
// GET    /products         -> index
// POST   /products         -> store  
// GET    /products/{id}    -> show
// PUT    /products/{id}    -> update
// DELETE /products/{id}    -> destroy
```

### Partial Resources

```php
// Only specific methods
RouteManager::resource('products', 'ProductController')
    ->only(['index', 'show']);

// Exclude specific methods
RouteManager::resource('products', 'ProductController')
    ->except(['destroy']);
```

### Nested Resources

```php
// Nested resources
RouteManager::resource('posts.comments', 'CommentController');

// Creates routes like:
// GET    /posts/{post}/comments
// POST   /posts/{post}/comments
// GET    /posts/{post}/comments/{comment}
// PUT    /posts/{post}/comments/{comment}
// DELETE /posts/{post}/comments/{comment}
```

## Route Groups

### Basic Groups

```php
// Group routes with common prefix
RouteManager::group(['prefix' => 'admin'], function() {
    RouteManager::get('users', 'AdminController@users');
    RouteManager::get('settings', 'AdminController@settings');
});

// Creates:
// /wp-json/myapp/v1/admin/users
// /wp-json/myapp/v1/admin/settings
```

### Middleware Groups

```php
// Apply middleware to all routes in group
RouteManager::group(['middleware' => ['auth']], function() {
    RouteManager::get('profile', 'ProfileController@show');
    RouteManager::put('profile', 'ProfileController@update');
    RouteManager::delete('account', 'AccountController@destroy');
});
```

### Namespace Groups

```php
// Different namespace for group
RouteManager::group(['namespace' => 'admin/v1'], function() {
    RouteManager::get('dashboard', 'DashboardController@index');
});

// Creates: /wp-json/admin/v1/dashboard
```

### Nested Groups

```php
// Nested groups
RouteManager::group(['prefix' => 'api', 'middleware' => ['auth']], function() {
    // Public authenticated routes
    RouteManager::get('profile', 'ProfileController@show');
    
    // Admin-only routes
    RouteManager::group(['prefix' => 'admin', 'middleware' => ['capability:manage_options']], function() {
        RouteManager::resource('users', 'AdminController');
        RouteManager::get('analytics', 'AnalyticsController@index');
    });
});
```

## Route Middleware

### Single Route Middleware

```php
RouteManager::get('protected', 'ProtectedController@index')
    ->middleware(['auth']);

RouteManager::post('upload', 'UploadController@store')
    ->middleware(['auth', 'rate_limit:10,1']);
```

### Middleware Parameters

```php
// Middleware with parameters
RouteManager::delete('posts/{id}', 'PostController@destroy')
    ->middleware(['capability:delete_posts']);

RouteManager::get('admin/data', 'AdminController@data')
    ->middleware(['role:administrator']);
```

## Route Model Binding

### Automatic Model Binding

```php
// If you're using WordPress ORM
RouteManager::get('products/{product}', 'ProductController@show');

// In controller:
public function show(WP_REST_Request $request, Product $product)
{
    // $product is automatically resolved from the {product} parameter
    return $this->success($product);
}
```

### Custom Route Model Binding

```php
// Custom binding logic
RouteManager::bind('post', function($value) {
    return get_post($value);
});

// Use in route
RouteManager::get('posts/{post}', function($request, $post) {
    // $post is the resolved WP_Post object
    return ['post' => $post];
});
```

## Route Caching

### Enable Route Caching

```php
// Cache routes for better performance
RouteManager::enableRouteCache(true);

// Custom cache duration (in seconds)
RouteManager::setCacheDuration(3600); // 1 hour
```

### Cache Invalidation

```php
// Clear route cache
RouteManager::clearRouteCache();

// Rebuild cache
RouteManager::rebuildRouteCache();
```

## Route Conditions

### Conditional Routes

```php
// Only register routes under certain conditions
if (current_user_can('manage_options')) {
    RouteManager::resource('admin/users', 'AdminUserController');
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    RouteManager::get('debug/info', 'DebugController@info');
}
```

### Environment-based Routes

```php
// Different routes for different environments
if (wp_get_environment_type() === 'development') {
    RouteManager::get('dev/test', 'TestController@index');
}

if (wp_get_environment_type() === 'production') {
    RouteManager::get('status', 'StatusController@health');
}
```

## Route Fallbacks

### 404 Fallback

```php
// Custom 404 handler
RouteManager::fallback(function() {
    return new WP_REST_Response([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});
```

## Advanced Routing

### Route Macros

```php
// Define reusable route patterns
RouteManager::macro('apiResource', function($name, $controller) {
    return RouteManager::resource($name, $controller)
        ->middleware(['auth', 'rate_limit:60,1']);
});

// Use macro
RouteManager::apiResource('products', 'ProductController');
```

### Route Subdomain

```php
// Routes for specific subdomains (if supported)
RouteManager::group(['domain' => 'api.example.com'], function() {
    RouteManager::resource('products', 'ProductController');
});
```

### HTTPS Only Routes

```php
// Require HTTPS for sensitive routes
RouteManager::group(['https'], function() {
    RouteManager::post('payment', 'PaymentController@process');
    RouteManager::get('admin/sensitive', 'AdminController@sensitive');
});
```

## Route Testing

### Testing Routes

```php
class RouteTest extends WP_UnitTestCase
{
    public function test_product_index_route()
    {
        // Make request to route
        $request = new WP_REST_Request('GET', '/wp-json/myapp/v1/products');
        $response = rest_do_request($request);
        
        // Assert response
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertIsArray($data);
    }
    
    public function test_protected_route_requires_auth()
    {
        // Test without authentication
        $request = new WP_REST_Request('GET', '/wp-json/myapp/v1/profile');
        $response = rest_do_request($request);
        
        $this->assertEquals(401, $response->get_status());
    }
}
```

## Route Debugging

### List All Routes

```bash
# List all registered routes
wp wproutes route:list

# Show route details
wp rest route list --format=table
```

### Debug Route Resolution

```php
// Debug route matching
add_action('rest_api_init', function() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        RouteManager::debugRoutes(true);
    }
});
```

## Best Practices

1. **Use Resource Routes**: For CRUD operations, use resource routes
2. **Group Related Routes**: Use route groups for organization
3. **Apply Middleware Consistently**: Use groups to apply middleware to multiple routes
4. **Name Important Routes**: Name routes you'll need to generate URLs for
5. **Use Parameter Constraints**: Validate route parameters with regex
6. **Cache Routes**: Enable route caching in production
7. **Test Routes**: Write tests for your API routes
8. **Document APIs**: Document your routes and their parameters

---

Next: [CLI Commands →](cli.md)