# WordPress Routes (WPRoutes)

A powerful API routing system for WordPress with middleware support. Create Laravel-like REST API endpoints that integrate seamlessly with WordPress REST API infrastructure.

## Features

- 🚀 **Laravel-style API routing** - Familiar syntax for creating powerful APIs
- 🛡️ **Middleware support** - Authentication, rate limiting, validation, and more
- 🔗 **WordPress integration** - Works with existing WordPress REST API
- ⚡ **High performance** - Optimized for speed with minimal overhead
- 🎯 **Clean architecture** - Following WordPress ORM patterns
- 📦 **No dependencies** - Just include and use

## Installation

Include the WPRoutes library in your WordPress theme or plugin:

```php
// In your theme's functions.php or plugin file
require_once get_template_directory() . '/lib/wp-routes/bootstrap.php';
```

## Quick Start

### Basic API Routes

```php
use WordPressRoutes\Routing\ApiManager;

// Simple GET endpoint
ApiManager::get('users', function($request) {
    return ['users' => get_users()];
});

// POST endpoint with data
ApiManager::post('users', function($request) {
    $data = $request->input();
    // Create user logic
    return ['created' => true];
});

// Route with parameters
ApiManager::get('users/(?P<id>[\d]+)', function($request) {
    $id = $request->param('id');
    return get_user_by('ID', $id);
});
```

### Using Middleware

```php
// Require authentication
ApiManager::get('profile', function($request) {
    return $request->user();
})->middleware('auth');

// Rate limiting
ApiManager::post('contact', function($request) {
    // Send email logic
    return ['sent' => true];
})->middleware(['rate_limit:10,60']); // 10 requests per minute

// Custom middleware
ApiManager::get('admin/users', function($request) {
    return get_users();
})->middleware(['auth', 'capability:manage_options']);
```

### Route Groups

```php
// Group routes with shared middleware and namespace
ApiManager::group([
    'namespace' => 'myapp/v1',
    'middleware' => ['auth']
], function() {
    ApiManager::get('dashboard', 'DashboardController@index');
    ApiManager::get('settings', 'SettingsController@index');
    ApiManager::post('settings', 'SettingsController@update');
});
```

### Resource Routes (CRUD)

```php
// Automatically creates index, show, store, update, destroy routes
ApiManager::resource('posts', 'PostController');

// Only specific actions
ApiManager::resource('comments', 'CommentController', [
    'only' => ['index', 'show', 'store']
]);
```

## Available Middleware

### Built-in Middleware

- **`auth`** - Requires user authentication
- **`capability`** - Requires specific WordPress capability
- **`rate_limit`** - Rate limiting (requests per minute)
- **`cors`** - CORS headers
- **`validate`** - Input validation
- **`json_only`** - Accept only JSON requests
- **`nonce`** - WordPress nonce verification

### Using Middleware

```php
// Single middleware
->middleware('auth')

// Multiple middleware
->middleware(['auth', 'rate_limit'])

// Middleware with parameters
->middleware('rate_limit:30,60') // 30 requests per minute

// Capability check
->middleware('capability:edit_posts')
```

## Request Methods

The `ApiRequest` object provides convenient methods to access request data:

```php
ApiManager::post('example', function($request) {
    // Get input data
    $name = $request->input('name');
    $email = $request->input('email', 'default@example.com');

    // Get all input
    $data = $request->all();

    // Get only specific fields
    $filtered = $request->only(['name', 'email']);

    // Check if field exists
    if ($request->has('optional_field')) {
        // Handle optional field
    }

    // Get route parameters
    $id = $request->param('id');

    // Get query parameters
    $page = $request->query('page', 1);

    // Get current user
    $user = $request->user();

    // File uploads
    if ($request->hasFile('avatar')) {
        $file = $request->file('avatar');
    }

    return ['success' => true];
});
```

## Response Formats

```php
// JSON response (automatic)
return ['data' => $data];

// WordPress REST Response
return new WP_REST_Response($data, 201);

// Error response
return new WP_Error('invalid_data', 'Invalid input', ['status' => 400]);

// Custom response with headers
$response = new WP_REST_Response($data);
$response->header('X-Custom-Header', 'value');
return $response;
```

## Custom Middleware

Create your own middleware by implementing the `MiddlewareInterface`:

```php
use WordPressRoutes\Routing\Middleware\MiddlewareInterface;
use WordPressRoutes\Routing\ApiRequest;

class CustomMiddleware implements MiddlewareInterface
{
    public function handle(ApiRequest $request)
    {
        // Your middleware logic
        if (!$this->isValid($request)) {
            return new WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        // Return null to continue processing
        return null;
    }
}

// Register middleware
MiddlewareRegistry::register('custom', CustomMiddleware::class);

// Use in routes
ApiManager::get('protected', $handler)->middleware('custom');
```

## Controller Classes

Organize your API logic with controller classes:

```php
class UserController
{
    public function index(ApiRequest $request)
    {
        return get_users();
    }

    public function show(ApiRequest $request)
    {
        $id = $request->param('id');
        return get_user_by('ID', $id);
    }

    public function store(ApiRequest $request)
    {
        $validation = $request->validate([
            'username' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|min:8'
        ]);

        if (is_wp_error($validation)) {
            return $validation;
        }

        // Create user logic
        return wp_create_user(
            $request->input('username'),
            $request->input('password'),
            $request->input('email')
        );
    }
}
```

## Integration with WordPress ORM

Perfect companion to WordPress ORM for database operations:

```php
use WordpressORM\Models\Post;

ApiManager::get('posts', function($request) {
    $posts = Post::where('post_status', 'publish')
        ->where('post_type', 'post')
        ->orderBy('post_date', 'desc')
        ->limit(10)
        ->get();

    return $posts;
})->middleware('rate_limit:100,60');

ApiManager::post('posts', function($request) {
    $validation = $request->validate([
        'title' => 'required|max:255',
        'content' => 'required',
        'status' => 'required'
    ]);

    if (is_wp_error($validation)) {
        return $validation;
    }

    $post = new Post();
    $post->post_title = $request->input('title');
    $post->post_content = $request->input('content');
    $post->post_status = $request->input('status');
    $post->post_author = $request->userId();
    $post->save();

    return $post;
})->middleware(['auth', 'capability:edit_posts']);
```

## Configuration

### Set Default Namespace

```php
// Set your app's API namespace
ApiManager::setNamespace('myapp/v1');
```

### Global Middleware

```php
// Apply middleware to all routes
ApiManager::middleware(['cors', 'rate_limit:1000,60']);
```

### Custom Middleware Registry

```php
// Register multiple middleware at once
MiddlewareRegistry::registerMany([
    'admin' => AdminMiddleware::class,
    'owner' => OwnershipMiddleware::class,
    'api_key' => ApiKeyMiddleware::class,
]);
```

## URL Generation

```php
// Generate URLs for your API endpoints
$url = ApiManager::url('user_profile', ['id' => 123]);
// Returns: https://yoursite.com/wp-json/myapp/v1/users/123

// In JavaScript (frontend)
const apiUrl = wpApiSettings.root + 'myapp/v1/users';
```

## Performance Tips

1. **Use rate limiting** to prevent abuse
2. **Validate input** to avoid processing invalid data
3. **Cache responses** for expensive operations
4. **Use pagination** for large datasets
5. **Minimize middleware** stack for high-frequency endpoints

## Security Best Practices

1. **Always validate input** using built-in validation
2. **Use authentication** for sensitive endpoints
3. **Check capabilities** for WordPress-specific permissions
4. **Implement rate limiting** to prevent brute force attacks
5. **Sanitize output** when returning user data
6. **Use HTTPS** in production
7. **Verify nonces** for state-changing operations

## Error Handling

```php
ApiManager::post('sensitive', function($request) {
    try {
        // Risky operation
        return performOperation();
    } catch (Exception $e) {
        return new WP_Error(
            'operation_failed',
            $e->getMessage(),
            ['status' => 500]
        );
    }
});
```

## Testing Your API

```bash
# Test with cURL
curl -X GET "https://yoursite.com/wp-json/myapp/v1/users"

curl -X POST "https://yoursite.com/wp-json/myapp/v1/users" \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com"}'
```

## Examples

### Complete User Management API

```php
// Set namespace
ApiManager::setNamespace('myapp/v1');

// Public routes
ApiManager::get('users', 'UserController@index');
ApiManager::get('users/(?P<id>[\d]+)', 'UserController@show');

// Protected routes
ApiManager::group(['middleware' => 'auth'], function() {
    ApiManager::get('profile', 'UserController@profile');
    ApiManager::put('profile', 'UserController@updateProfile');
    ApiManager::post('avatar', 'UserController@uploadAvatar');
});

// Admin only routes
ApiManager::group([
    'middleware' => ['auth', 'capability:manage_users'],
    'prefix' => 'admin'
], function() {
    ApiManager::resource('users', 'Admin\UserController');
    ApiManager::post('users/(?P<id>[\d]+)/ban', 'Admin\UserController@ban');
});
```

This creates a complete user management API with public listing, protected profile management, and admin-only user administration.

## Compatibility

- WordPress 5.0+
- PHP 8.0+
- Works with any WordPress theme or plugin
- Compatible with WordPress ORM
- Integrates with existing WordPress REST API

## License

Same as WordPress ORM - feel free to use in any project.
