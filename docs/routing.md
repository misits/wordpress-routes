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

use WordPressRoutes\Routing\ApiManager;

// Set API namespace for all routes
ApiManager::setNamespace('wp/v2');

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
| These routes are accessible without authentication
*/

// Simple endpoint
ApiManager::get('health', function($request) {
    return ['status' => 'ok', 'timestamp' => current_time('mysql')];
});

// Resource routes (CRUD)
route_resource('posts', 'PostController', [
    'only' => ['index', 'show'] // Public read-only
]);

// Search endpoint
ApiManager::get('search', function($request) {
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

ApiManager::group(['middleware' => 'auth'], function() {
    // User's own posts - full CRUD
    route_resource('my/posts', 'PostController', [
        'only' => ['store', 'update', 'destroy']
    ]);
    
    // Profile management
    ApiManager::get('profile', 'UserController@profile');
    ApiManager::put('profile', 'UserController@updateProfile');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| These routes require admin privileges
*/

ApiManager::group([
    'middleware' => ['auth', 'capability:manage_options'],
    'prefix' => 'admin'
], function() {
    // Admin-only full access
    route_resource('posts', 'PostController');
    route_resource('users', 'UserController');
    
    // System management
    ApiManager::get('stats', 'AdminController@stats');
    ApiManager::post('cache/clear', 'AdminController@clearCache');
});

/*
|--------------------------------------------------------------------------
| API Versioning Example
|--------------------------------------------------------------------------
*/

// V2 API with enhanced features
ApiManager::group(['namespace' => 'myapp/v2'], function() {
    route_resource('posts', 'V2\\PostController');
});

/*
|--------------------------------------------------------------------------
| Development Routes
|--------------------------------------------------------------------------
| Only available when WP_DEBUG is enabled
*/

if (defined('WP_DEBUG') && WP_DEBUG) {
    ApiManager::get('debug/routes', function($request) {
        $routes = ApiManager::getRoutes();
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
use WordPressRoutes\Routing\ApiManager;

add_action('rest_api_init', function() {
    // Set API namespace
    ApiManager::setNamespace('myapp/v1');
    
    // Basic routes
    ApiManager::get('products', 'ProductController@index');
    ApiManager::post('products', 'ProductController@store');
    ApiManager::get('products/{id}', 'ProductController@show');
    ApiManager::put('products/{id}', 'ProductController@update');
    ApiManager::delete('products/{id}', 'ProductController@destroy');
});
```

### Available Route Methods

```php
// HTTP GET
ApiManager::get('users', 'UserController@index');

// HTTP POST
ApiManager::post('users', 'UserController@store');

// HTTP PUT
ApiManager::put('users/{id}', 'UserController@update');

// HTTP PATCH
ApiManager::patch('users/{id}', 'UserController@partialUpdate');

// HTTP DELETE
ApiManager::delete('users/{id}', 'UserController@destroy');

// Multiple HTTP methods
ApiManager::match(['GET', 'POST'], 'search', 'SearchController@handle');

// Any HTTP method
ApiManager::any('webhook', 'WebhookController@handle');
```

### Route Parameters

```php
// Required parameters
ApiManager::get('users/{id}', 'UserController@show');
ApiManager::get('posts/{post}/comments/{comment}', 'CommentController@show');

// Optional parameters
ApiManager::get('products/{category?}', 'ProductController@index');

// Parameter constraints
ApiManager::get('users/{id}', 'UserController@show')
    ->where('id', '[0-9]+'); // Only numeric IDs

ApiManager::get('posts/{slug}', 'PostController@show')
    ->where('slug', '[a-z-]+'); // Only lowercase letters and dashes
```

## Route Naming

### Named Routes

```php
// Name routes for URL generation
ApiManager::get('products', 'ProductController@index')
    ->name('products.index');

ApiManager::get('products/{id}', 'ProductController@show')
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
ApiManager::resource('products', 'ProductController');

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
ApiManager::resource('products', 'ProductController')
    ->only(['index', 'show']);

// Exclude specific methods
ApiManager::resource('products', 'ProductController')
    ->except(['destroy']);
```

### Nested Resources

```php
// Nested resources
ApiManager::resource('posts.comments', 'CommentController');

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
ApiManager::group(['prefix' => 'admin'], function() {
    ApiManager::get('users', 'AdminController@users');
    ApiManager::get('settings', 'AdminController@settings');
});

// Creates:
// /wp-json/myapp/v1/admin/users
// /wp-json/myapp/v1/admin/settings
```

### Middleware Groups

```php
// Apply middleware to all routes in group
ApiManager::group(['middleware' => ['auth']], function() {
    ApiManager::get('profile', 'ProfileController@show');
    ApiManager::put('profile', 'ProfileController@update');
    ApiManager::delete('account', 'AccountController@destroy');
});
```

### Namespace Groups

```php
// Different namespace for group
ApiManager::group(['namespace' => 'admin/v1'], function() {
    ApiManager::get('dashboard', 'DashboardController@index');
});

// Creates: /wp-json/admin/v1/dashboard
```

### Nested Groups

```php
// Nested groups
ApiManager::group(['prefix' => 'api', 'middleware' => ['auth']], function() {
    // Public authenticated routes
    ApiManager::get('profile', 'ProfileController@show');
    
    // Admin-only routes
    ApiManager::group(['prefix' => 'admin', 'middleware' => ['capability:manage_options']], function() {
        ApiManager::resource('users', 'AdminController');
        ApiManager::get('analytics', 'AnalyticsController@index');
    });
});
```

## Route Middleware

### Single Route Middleware

```php
ApiManager::get('protected', 'ProtectedController@index')
    ->middleware(['auth']);

ApiManager::post('upload', 'UploadController@store')
    ->middleware(['auth', 'rate_limit:10,1']);
```

### Middleware Parameters

```php
// Middleware with parameters
ApiManager::delete('posts/{id}', 'PostController@destroy')
    ->middleware(['capability:delete_posts']);

ApiManager::get('admin/data', 'AdminController@data')
    ->middleware(['role:administrator']);
```

## Route Model Binding

### Automatic Model Binding

```php
// If you're using WordPress ORM
ApiManager::get('products/{product}', 'ProductController@show');

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
ApiManager::bind('post', function($value) {
    return get_post($value);
});

// Use in route
ApiManager::get('posts/{post}', function($request, $post) {
    // $post is the resolved WP_Post object
    return ['post' => $post];
});
```

## Route Caching

### Enable Route Caching

```php
// Cache routes for better performance
ApiManager::enableRouteCache(true);

// Custom cache duration (in seconds)
ApiManager::setCacheDuration(3600); // 1 hour
```

### Cache Invalidation

```php
// Clear route cache
ApiManager::clearRouteCache();

// Rebuild cache
ApiManager::rebuildRouteCache();
```

## Route Conditions

### Conditional Routes

```php
// Only register routes under certain conditions
if (current_user_can('manage_options')) {
    ApiManager::resource('admin/users', 'AdminUserController');
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    ApiManager::get('debug/info', 'DebugController@info');
}
```

### Environment-based Routes

```php
// Different routes for different environments
if (wp_get_environment_type() === 'development') {
    ApiManager::get('dev/test', 'TestController@index');
}

if (wp_get_environment_type() === 'production') {
    ApiManager::get('status', 'StatusController@health');
}
```

## Route Fallbacks

### 404 Fallback

```php
// Custom 404 handler
ApiManager::fallback(function() {
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
ApiManager::macro('apiResource', function($name, $controller) {
    return ApiManager::resource($name, $controller)
        ->middleware(['auth', 'rate_limit:60,1']);
});

// Use macro
ApiManager::apiResource('products', 'ProductController');
```

### Route Subdomain

```php
// Routes for specific subdomains (if supported)
ApiManager::group(['domain' => 'api.example.com'], function() {
    ApiManager::resource('products', 'ProductController');
});
```

### HTTPS Only Routes

```php
// Require HTTPS for sensitive routes
ApiManager::group(['https'], function() {
    ApiManager::post('payment', 'PaymentController@process');
    ApiManager::get('admin/sensitive', 'AdminController@sensitive');
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
        ApiManager::debugRoutes(true);
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