# Middleware

Middleware provides a convenient mechanism for filtering HTTP requests entering your WordPress API. Middleware can perform authentication, logging, rate limiting, and other tasks before requests reach your controllers.

## Creating Middleware

### Using CLI (Recommended)

Generate middleware using WP-CLI:

```bash
# Basic middleware
wp borps routes:make-middleware AuthMiddleware

# Custom namespace
wp borps routes:make-middleware AuthMiddleware --namespace="MyApp\\Middleware"

# Custom path
wp borps routes:make-middleware AuthMiddleware --path=/custom/path
```

### Manual Creation

Create middleware manually:

```php
<?php
// middleware/AuthMiddleware.php

use WordPressRoutes\Routing\Middleware\MiddlewareInterface;
use WP_REST_Request;
use WP_REST_Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        // Pre-processing logic
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Continue to next middleware or controller
        $response = $next($request);

        // Post-processing logic (optional)

        return $response;
    }
}
```

## Built-in Middleware

WordPress Routes comes with several built-in middleware:

### Authentication Middleware

```php
use WordPressRoutes\Routing\Route;

// Require authentication
Route::get('protected', 'ProtectedController@index')
    ->middleware(['auth']);

// Multiple middleware
Route::get('admin', 'AdminController@index')
    ->middleware(['auth', 'capability:manage_options']);
```

### Rate Limiting Middleware

```php
// Limit to 60 requests per minute
Route::get('api/data', 'DataController@index')
    ->middleware(['rate_limit:60,1']);

// Limit to 1000 requests per hour
Route::post('api/upload', 'UploadController@store')
    ->middleware(['rate_limit:1000,60']);
```

### Capability Middleware

```php
// Require specific capability
Route::delete('posts/{id}', 'PostController@destroy')
    ->middleware(['capability:delete_posts']);

// Multiple capabilities (AND logic)
Route::get('admin/users', 'AdminController@users')
    ->middleware(['capability:list_users,manage_options']);
```

## Custom Middleware

### Basic Custom Middleware

```php
<?php
class LoggingMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        // Log request
        error_log('API Request: ' . $request->get_route());

        $startTime = microtime(true);

        // Process request
        $response = $next($request);

        // Log response time
        $duration = microtime(true) - $startTime;
        error_log('Response time: ' . round($duration * 1000, 2) . 'ms');

        return $response;
    }
}
```

### Middleware with Parameters

```php
<?php
class RoleMiddleware implements MiddlewareInterface
{
    private $requiredRole;

    public function __construct($requiredRole = 'subscriber')
    {
        $this->requiredRole = $requiredRole;
    }

    public function handle(WP_REST_Request $request, callable $next)
    {
        $user = wp_get_current_user();

        if (!$user->exists()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        if (!in_array($this->requiredRole, $user->roles)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return $next($request);
    }
}
```

### CORS Middleware

```php
<?php
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        // Handle preflight requests
        if ($request->get_method() === 'OPTIONS') {
            return new WP_REST_Response(null, 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Max-Age' => '86400'
            ]);
        }

        // Process request
        $response = $next($request);

        // Add CORS headers to response
        if ($response instanceof WP_REST_Response) {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
```

## Applying Middleware

### Single Route Middleware

```php
use WordPressRoutes\Routing\Route;

// Single middleware
Route::get('products', 'ProductController@index')
    ->middleware(['auth']);

// Multiple middleware
Route::post('products', 'ProductController@store')
    ->middleware(['auth', 'rate_limit:30,1', 'logging']);
```

### Route Group Middleware

```php
// Apply middleware to multiple routes
Route::group(['middleware' => ['auth']], function() {
    Route::get('profile', 'ProfileController@show');
    Route::put('profile', 'ProfileController@update');
    Route::delete('account', 'AccountController@destroy');
});

// Nested groups with different middleware
Route::group(['middleware' => ['auth']], function() {
    // User routes
    Route::get('profile', 'ProfileController@show');

    // Admin routes (additional middleware)
    Route::group(['middleware' => ['capability:manage_options']], function() {
        Route::get('users', 'AdminController@users');
        Route::delete('users/{id}', 'AdminController@deleteUser');
    });
});
```

### Global Middleware

Apply middleware to all routes:

```php
// In your theme/plugin initialization
add_action('rest_api_init', function() {
    // Register global middleware
    Route::globalMiddleware(['cors', 'logging']);

    // Your routes...
    Route::resource('products', 'ProductController');
});
```

## Middleware Registration

### Manual Registration

Register middleware manually:

```php
use WordPressRoutes\Routing\MiddlewareRegistry;

// Register single middleware
MiddlewareRegistry::register('custom_auth', CustomAuthMiddleware::class);

// Register with parameters
MiddlewareRegistry::register('role', function($role = 'subscriber') {
    return new RoleMiddleware($role);
});
```

### Auto-Registration

Middleware are auto-discovered from registered paths:

```php
// These middleware will be auto-discovered:
// middleware/AuthMiddleware.php -> 'auth'
// middleware/LoggingMiddleware.php -> 'logging'
// middleware/RateLimitMiddleware.php -> 'rate_limit'
```

## Advanced Middleware Patterns

### Conditional Middleware

```php
class ConditionalMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        // Apply different logic based on conditions
        if ($this->isAdminRequest($request)) {
            return $this->handleAdminRequest($request, $next);
        }

        if ($this->isRouteRequest($request)) {
            return $this->handleRouteRequest($request, $next);
        }

        return $next($request);
    }

    private function isAdminRequest($request)
    {
        return strpos($request->get_route(), '/admin/') === 0;
    }

    private function isRouteRequest($request)
    {
        return strpos($request->get_route(), '/api/') === 0;
    }
}
```

### Middleware with Dependencies

```php
class CacheMiddleware implements MiddlewareInterface
{
    private $cache;

    public function __construct()
    {
        // Initialize cache (Redis, Memcached, etc.)
        $this->cache = wp_cache_init();
    }

    public function handle(WP_REST_Request $request, callable $next)
    {
        // Only cache GET requests
        if ($request->get_method() !== 'GET') {
            return $next($request);
        }

        $cacheKey = $this->getCacheKey($request);

        // Try to get from cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return new WP_REST_Response($cached);
        }

        // Process request
        $response = $next($request);

        // Cache the response
        if ($response instanceof WP_REST_Response && $response->get_status() === 200) {
            $this->cache->set($cacheKey, $response->get_data(), 300); // 5 minutes
        }

        return $response;
    }

    private function getCacheKey($request)
    {
        return 'api_cache_' . md5($request->get_route() . serialize($request->get_params()));
    }
}
```

## Middleware Testing

### Unit Testing

```php
class AuthMiddlewareTest extends WP_UnitTestCase
{
    public function test_authenticated_user_passes()
    {
        // Arrange
        wp_set_current_user(1); // Set user
        $request = new WP_REST_Request('GET', '/test');
        $next = function($request) {
            return new WP_REST_Response(['success' => true]);
        };

        // Act
        $middleware = new AuthMiddleware();
        $response = $middleware->handle($request, $next);

        // Assert
        $this->assertEquals(200, $response->get_status());
    }

    public function test_unauthenticated_user_fails()
    {
        // Arrange
        wp_set_current_user(0); // No user
        $request = new WP_REST_Request('GET', '/test');
        $next = function($request) {
            return new WP_REST_Response(['success' => true]);
        };

        // Act
        $middleware = new AuthMiddleware();
        $response = $middleware->handle($request, $next);

        // Assert
        $this->assertEquals(401, $response->get_status());
    }
}
```

## Middleware Discovery

List all discovered middleware:

```bash
wp borps routes:middleware-list
wp borps routes:middleware-list --format=json
```

## Best Practices

1. **Keep Middleware Focused**: Each middleware should have a single responsibility
2. **Order Matters**: Apply middleware in the correct order (auth before rate limiting)
3. **Handle Errors Gracefully**: Return appropriate HTTP status codes
4. **Performance**: Avoid heavy operations in frequently used middleware
5. **Reusability**: Write middleware that can be reused across different routes
6. **Documentation**: Document middleware behavior and parameters
7. **Testing**: Write tests for your custom middleware

---

Next: [Routing →](routing.md)
