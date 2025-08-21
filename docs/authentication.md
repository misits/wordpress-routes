# Authentication

WordPress Routes provides robust authentication support for protecting your API endpoints. It integrates seamlessly with WordPress's authentication system while offering modern API authentication patterns.

## Authentication Methods

### 1. Nonce-Based Authentication (Recommended for Frontend)

WordPress nonces provide CSRF protection and user authentication for same-origin requests.

```php
// Generate nonce in PHP (for frontend use)
$nonce = wp_create_nonce('wp_rest');
```

```javascript
// Use nonce in JavaScript
fetch('/wp-json/myapp/v1/profile', {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
        'X-WP-Nonce': '<?php echo wp_create_nonce("wp_rest"); ?>',
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

### 2. Cookie Authentication

WordPress automatically handles cookie authentication for logged-in users when making same-origin requests.

```javascript
// Cookie auth with credentials
fetch('/wp-json/myapp/v1/profile', {
    method: 'GET',
    credentials: 'same-origin'  // Important: includes cookies
})
.then(response => response.json())
.then(data => console.log(data));
```

### 3. Application Passwords (WordPress 5.6+)

For external applications and services:

```bash
# Create application password in WordPress Admin
# Users -> Profile -> Application Passwords

# Use in requests
curl -u "username:application_password" \
  https://yoursite.com/wp-json/myapp/v1/profile
```

### 4. Custom Token Authentication

Implement JWT or API key authentication:

```php
// Create custom auth middleware
class ApiKeyMiddleware implements MiddlewareInterface
{
    public function handle(RouteRequest $request)
    {
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey || !$this->validateApiKey($apiKey)) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid or missing API key',
                ['status' => 401]
            );
        }
        
        return null;
    }
    
    private function validateApiKey($key)
    {
        // Your validation logic
        return get_option('api_key_' . $key) !== false;
    }
}
```

## Using Authentication in Routes

### Protecting Individual Routes

```php
use WordPressRoutes\Routing\RouteManager;

// Require authentication for single route
RouteManager::get('profile', function($request) {
    $user = $request->user();
    return [
        'id' => $user->ID,
        'name' => $user->display_name,
        'email' => $user->user_email
    ];
})->middleware('auth');
```

### Protecting Route Groups

```php
// All routes in group require authentication
RouteManager::group(['middleware' => 'auth'], function() {
    RouteManager::get('profile', 'UserController@profile');
    RouteManager::put('profile', 'UserController@updateProfile');
    RouteManager::post('avatar', 'UserController@uploadAvatar');
    
    // Nested group with additional requirements
    RouteManager::group(['middleware' => 'capability:manage_options'], function() {
        RouteManager::get('admin/dashboard', 'AdminController@dashboard');
        RouteManager::get('admin/users', 'AdminController@users');
    });
});
```

## Authentication in Controllers

### Using RouteRequest Authentication Methods

```php
use WordPressRoutes\Routing\BaseController;
use WordPressRoutes\Routing\RouteRequest;

class UserController extends BaseController
{
    public function profile(RouteRequest $request)
    {
        // Check if authenticated
        if (!$request->isAuthenticated()) {
            return $this->unauthorized();
        }
        
        // Get current user
        $user = $request->user();
        
        // Check specific capability
        if (!$request->userCan('edit_posts')) {
            return $this->forbidden();
        }
        
        return $this->success([
            'user' => [
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'roles' => $user->roles
            ]
        ]);
    }
}
```

### BaseController Authentication Helpers

```php
class SecureController extends BaseController
{
    public function adminAction(RouteRequest $request)
    {
        // Authorize checks authentication AND capability
        $authorized = $this->authorize('manage_options');
        if (is_wp_error($authorized)) {
            return $authorized;
        }
        
        // Proceed with admin action
        return $this->success(['message' => 'Admin action completed']);
    }
    
    public function userAction(RouteRequest $request)
    {
        // Check authentication status
        if (!$this->isAuthenticated()) {
            return $this->unauthorized(); // Returns 401
        }
        
        // Check specific permission
        if (!$this->can('edit_posts')) {
            return $this->forbidden(); // Returns 403
        }
        
        return $this->success(['message' => 'Action completed']);
    }
}
```

## Testing Authenticated Endpoints

### Using the Built-in Testing Interface

WordPress Routes provides a testing interface with authentication support:

1. Navigate to the Routes tab in your theme
2. Enter the API endpoint URL
3. Click "Test GET (Auth)" to test with authentication
4. The interface automatically includes the nonce header

### Testing with cURL

```bash
# With nonce (get nonce from WordPress)
curl -X GET "https://yoursite.com/wp-json/myapp/v1/profile" \
  -H "X-WP-Nonce: YOUR_NONCE_HERE"

# With application password
curl -u "username:application_password" \
  https://yoursite.com/wp-json/myapp/v1/profile

# With custom header
curl -X GET "https://yoursite.com/wp-json/myapp/v1/profile" \
  -H "X-API-Key: your-api-key-here"
```

### Testing with Postman

1. **Nonce Authentication:**
   - Add header: `X-WP-Nonce: your_nonce_here`
   - Enable cookies in Postman settings

2. **Basic Authentication:**
   - Authorization tab → Basic Auth
   - Enter WordPress username and application password

3. **Custom Headers:**
   - Headers tab → Add `X-API-Key: your_key`

## Common Authentication Patterns

### Public and Private Endpoints

```php
// Public endpoints (no auth required)
RouteManager::get('products', 'ProductController@index');
RouteManager::get('products/{id}', 'ProductController@show');

// Private endpoints (auth required)
RouteManager::group(['middleware' => 'auth'], function() {
    RouteManager::post('products', 'ProductController@store');
    RouteManager::put('products/{id}', 'ProductController@update');
    RouteManager::delete('products/{id}', 'ProductController@destroy');
});
```

### Role-Based Access Control

```php
// Different access levels
RouteManager::group(['middleware' => 'auth'], function() {
    // All authenticated users
    RouteManager::get('dashboard', 'DashboardController@index');
    
    // Editors and above
    RouteManager::group(['middleware' => 'capability:edit_posts'], function() {
        RouteManager::resource('posts', 'PostController');
    });
    
    // Administrators only
    RouteManager::group(['middleware' => 'capability:manage_options'], function() {
        RouteManager::resource('users', 'UserController');
        RouteManager::get('settings', 'SettingsController@index');
        RouteManager::put('settings', 'SettingsController@update');
    });
});
```

### API Versioning with Auth

```php
// V1 API - Basic auth
RouteManager::group(['namespace' => 'api/v1', 'middleware' => 'auth'], function() {
    RouteManager::resource('orders', 'V1\OrderController');
});

// V2 API - Token auth
RouteManager::group(['namespace' => 'api/v2', 'middleware' => 'token_auth'], function() {
    RouteManager::resource('orders', 'V2\OrderController');
});
```

## Security Best Practices

### 1. Always Use HTTPS in Production

```php
// Force HTTPS for API routes
if (!is_ssl() && !WP_DEBUG) {
    wp_die('API requires HTTPS connection', 'Secure Connection Required', 403);
}
```

### 2. Implement Rate Limiting

```php
// Combine auth with rate limiting
RouteManager::post('api/action', 'ApiController@action')
    ->middleware(['auth', 'rate_limit:60,1']); // 60 requests per minute
```

### 3. Validate Permissions Granularly

```php
public function updatePost(RouteRequest $request)
{
    $post_id = $request->param('id');
    
    // Check if user can edit THIS specific post
    if (!current_user_can('edit_post', $post_id)) {
        return $this->forbidden();
    }
    
    // Proceed with update
}
```

### 4. Use Nonces for State-Changing Operations

```php
public function deleteResource(RouteRequest $request)
{
    // Verify nonce for destructive operations
    $nonce = $request->header('X-WP-Nonce') ?: $request->query('_wpnonce');
    
    if (!wp_verify_nonce($nonce, 'delete_resource_' . $request->param('id'))) {
        return new WP_Error('invalid_nonce', 'Security check failed', ['status' => 403]);
    }
    
    // Proceed with deletion
}
```

## Troubleshooting Authentication

### Common Issues and Solutions

1. **401 Unauthorized Error**
   - Ensure user is logged in
   - Check nonce is valid and not expired
   - Verify cookies are being sent (same-origin)

2. **403 Forbidden Error**
   - User lacks required capabilities
   - Check user roles and permissions

3. **Nonce Validation Failing**
   - Nonces expire after 24 hours (default)
   - Ensure nonce action matches ('wp_rest')
   - Check user session is active

4. **Cookie Auth Not Working**
   - Ensure `credentials: 'same-origin'` in fetch
   - Check same-origin policy
   - Verify WordPress login cookies are set

### Debug Authentication

```php
// Add debug endpoint (development only)
if (WP_DEBUG) {
    RouteManager::get('debug/auth', function($request) {
        return [
            'is_authenticated' => $request->isAuthenticated(),
            'user_id' => get_current_user_id(),
            'user' => wp_get_current_user(),
            'nonce_valid' => wp_verify_nonce(
                $request->header('X-WP-Nonce') ?: $request->query('_wpnonce'),
                'wp_rest'
            ),
            'capabilities' => array_keys(wp_get_current_user()->allcaps)
        ];
    });
}
```

## Next Steps

- [Security Best Practices →](security.md)
- [Middleware Documentation →](middleware.md)
- [Testing Guide →](testing.md)