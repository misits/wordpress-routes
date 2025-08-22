# WordPress Routes (WPRoutes)

A unified routing system for WordPress supporting **API, Web, Admin, and AJAX routes** with Laravel-style syntax, middleware support, and flexible template system.

## Features

- 🌐 **4 Route Types** - API, Web, Admin, and AJAX routes in one unified system
- 📄 **Template Support** - Custom templates for Web and Admin routes with data binding
- 🚀 **Laravel-style syntax** - Familiar, elegant routing syntax
- 🛡️ **Middleware support** - Authentication, rate limiting, validation, CORS, and more
- 🔗 **WordPress integration** - Works with existing WordPress systems
- 📦 **Plugin & Theme modes** - Flexible deployment options
- ⚡ **High performance** - Optimized with simplified architecture
- 🎯 **PHP 8+ features** - Modern PHP with union types, match expressions

## Installation

Include the WPRoutes library in your WordPress theme or plugin:

```php
// In your theme's functions.php or plugin file
require_once get_template_directory() . '/lib/wp-routes/bootstrap.php';
```

## Zero-Configuration Setup

WordPress Routes automatically loads your routes! Just create a `routes.php` file and it's automatically detected based on your `WPROUTES_MODE`:

### Theme Mode (Default)
```php
// Simply create: /wp-content/themes/your-theme/routes.php
<?php
route_resource('posts', 'PostController');
RouteManager::get('health', function($request) {
    return ['status' => 'ok'];
});
```

### Plugin Mode  
```php
// Set mode in your plugin:
define('WPROUTES_MODE', 'plugin');

// Create: /wp-content/plugins/your-plugin/routes.php
<?php
route_resource('products', 'ProductController');
```

### Auto-Detection Locations

**Theme Mode:** Searches in this order:
- `{child-theme}/routes.php`
- `{child-theme}/routes/api.php` 
- `{child-theme}/api/routes.php`
- `{parent-theme}/routes.php`
- `{parent-theme}/routes/api.php`
- `{parent-theme}/api/routes.php`

**Plugin Mode:** Searches in this order:
- `{plugin-root}/routes.php`
- `{plugin-root}/routes/api.php`
- `{plugin-root}/src/routes.php`

**That's it!** No manual route loading required. The library handles everything automatically.

### Disable Auto-Loading (Optional)
```php
// To disable automatic route loading
define('WPROUTES_NO_AUTO_ROUTES', true);
require_once get_template_directory() . '/lib/wp-routes/bootstrap.php';

// Then manually load your routes
require_once get_template_directory() . '/my-custom-routes.php';
```

## Quick Start - Unified Route System

### The New Route Class
WordPress Routes now provides a **unified Route class** that handles all route types with a consistent API:

```php
use WordPressRoutes\Routing\Route;

// API Routes (REST API endpoints)
Route::get('users', function($request) {
    return ['users' => get_users()];
});

// Web Routes (frontend pages with templates)
Route::web('about', function($request) {
    return ['projects' => get_posts()];
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

### Route Types Overview

#### 1. API Routes
Traditional REST API endpoints accessible via `/wp-json/your-namespace/endpoint`:

```php
Route::get('posts', function($request) {
    return get_posts();
})->middleware('auth');

Route::post('contact', function($request) {
    $data = $request->validated();
    // Process contact form
    return ['message' => 'Email sent'];
})->validate(['email' => 'required|email']);
```

#### 2. Web Routes
Frontend pages with custom templates and WordPress integration:

```php
Route::web('portfolio', function($request) {
    return [
        'projects' => get_posts(['post_type' => 'project']),
        'skills' => get_option('skills')
    ];
})
->template('portfolio.php')  // Uses theme/portfolio.php
->title('My Portfolio');
```

#### 3. Admin Routes  
WordPress admin dashboard pages with full template support:

```php
Route::admin('analytics', 'Analytics Dashboard', function($request) {
    return [
        'stats' => get_analytics_data(),
        'charts' => get_chart_data()
    ];
})
->template('admin/analytics.php')  // Custom admin template
->capability('manage_options')
->icon('dashicons-chart-bar')
->position(25);
```

#### 4. AJAX Routes
WordPress AJAX handlers for both authenticated and public requests:

```php
Route::ajax('update_settings', function($request) {
    update_option('my_setting', $request->input('value'));
    return ['updated' => true];
})->auth();  // Requires authentication

Route::ajax('public_data', function($request) {
    return ['data' => get_public_data()];
})->public();  // No authentication required
```

### Using Middleware

```php
// Require authentication
RouteManager::get('profile', function($request) {
    return $request->user();
})->middleware('auth');

// Rate limiting
RouteManager::post('contact', function($request) {
    // Send email logic
    return ['sent' => true];
})->middleware(['rate_limit:10,60']); // 10 requests per minute

// Custom middleware
RouteManager::get('admin/users', function($request) {
    return get_users();
})->middleware(['auth', 'capability:manage_options']);
```

### Route Groups

```php
// Group routes with shared middleware and namespace
RouteManager::group([
    'namespace' => 'myapp/v1',
    'middleware' => ['auth']
], function() {
    RouteManager::get('dashboard', 'DashboardController@index');
    RouteManager::get('settings', 'SettingsController@index');
    RouteManager::post('settings', 'SettingsController@update');
});
```

### Resource Routes (CRUD)

```php
// Automatically creates index, show, store, update, destroy routes
RouteManager::resource('posts', 'PostController');

// Only specific actions
RouteManager::resource('comments', 'CommentController', [
    'only' => ['index', 'show', 'store']
]);
```

## Template System

WordPress Routes provides a powerful template system for Web and Admin routes with support for both **Theme** and **Plugin** modes.

### Web Route Templates

Web routes can use custom templates with data binding:

```php
Route::web('about', function($request) {
    return [
        'company' => 'My Company',
        'team' => get_users(['role' => 'author']),
        'projects' => get_posts(['post_type' => 'project'])
    ];
})->template('about.php');
```

**Template file** (`theme/about.php`):
```php
<?php
// Data is available via $GLOBALS['route_data'] or extracted variables
$company = $company ?? $GLOBALS['route_data']['company'] ?? 'Default Company';
$team = $team ?? $GLOBALS['route_data']['team'] ?? [];

get_header();
?>
<h1>About <?php echo esc_html($company); ?></h1>
<div class="team">
    <?php foreach ($team as $member): ?>
        <div class="member"><?php echo esc_html($member->display_name); ?></div>
    <?php endforeach; ?>
</div>
<?php get_footer(); ?>
```

### Admin Route Templates

Admin routes support custom templates for creating rich dashboard interfaces:

```php
Route::admin('analytics', 'Analytics Dashboard', function($request) {
    return [
        'total_users' => count(get_users()),
        'recent_posts' => get_posts(['numberposts' => 5]),
        'stats' => get_analytics_data()
    ];
})
->template('admin-analytics.php')
->icon('dashicons-chart-bar');
```

**Admin template** (`theme/admin-analytics.php`):
```php
<?php
$data = $GLOBALS['admin_route_data'] ?? [];
$request = $GLOBALS['admin_route_request'] ?? [];
?>
<div class="wrap">
    <h1>Analytics Dashboard</h1>
    
    <div class="dashboard-widgets-wrap">
        <div class="postbox">
            <h2>Total Users</h2>
            <p class="big-number"><?php echo intval($data['total_users']); ?></p>
        </div>
        
        <div class="postbox">
            <h2>Recent Posts</h2>
            <?php foreach ($data['recent_posts'] as $post): ?>
                <p><?php echo esc_html($post->post_title); ?></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
```

### Template Resolution

Templates are resolved in the following order based on `WPROUTES_MODE`:

#### Theme Mode (Default)
```php
define('WPROUTES_MODE', 'theme'); // or omit for default
```

**Template resolution:**
1. `child-theme/template.php`
2. `parent-theme/template.php`
3. WordPress `locate_template()` standard

**Path support:**
```php
->template('page.php')              // Simple filename
->template('admin/settings.php')   // Subfolder path
->template('/absolute/path.php')   // Absolute path
```

#### Plugin Mode
```php
define('WPROUTES_MODE', 'plugin');
```

**Web routes search:**
1. `plugin/templates/template.php`
2. `plugin/views/template.php`
3. `plugin/template.php`
4. **Fallback to theme**

**Admin routes search:**
1. `plugin/admin-templates/template.php`
2. `plugin/templates/admin/template.php`
3. `plugin/templates/template.php`
4. `plugin/views/admin/template.php`
5. `plugin/views/template.php`
6. `plugin/template.php`
7. **Fallback to theme**

### Template Examples by Mode

**Theme Mode:**
```php
// These work in theme mode
->template('about.php')                    // theme/about.php
->template('pages/portfolio.php')         // theme/pages/portfolio.php
->template('admin/dashboard.php')         // theme/admin/dashboard.php
```

**Plugin Mode:**
```php
// These work in plugin mode
->template('about.php')                    // plugin/templates/about.php
->template('admin/dashboard.php')         // plugin/admin-templates/dashboard.php
->template('views/settings.php')          // plugin/views/settings.php
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

The `RouteRequest` object provides convenient methods to access request data:

```php
use WordPressRoutes\Routing\RouteRequest;

RouteManager::post('example', function(RouteRequest $request) {
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

    // Check authentication
    if ($request->isAuthenticated()) {
        $user = $request->user();
    }

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
use WordPressRoutes\Routing\RouteRequest;

class CustomMiddleware implements MiddlewareInterface
{
    public function handle(RouteRequest $request)
    {
        // Your middleware logic
        if (!$this->isValid($request)) {
            return new WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }

        // Return null to continue processing
        return null;
    }
    
    private function isValid(RouteRequest $request)
    {
        // Example validation logic
        return $request->isAuthenticated() && $request->userCan('read');
    }
}

// Register middleware
MiddlewareRegistry::register('custom', CustomMiddleware::class);

// Use in routes
RouteManager::get('protected', $handler)->middleware('custom');
```

## Controller Classes

Organize your API logic with controller classes:

```php
use WordPressRoutes\Routing\BaseController;
use WordPressRoutes\Routing\RouteRequest;

class UserController extends BaseController
{
    public function index(RouteRequest $request)
    {
        $users = get_users();
        return $this->success($users);
    }

    public function show(RouteRequest $request)
    {
        $id = $request->param('id');
        $user = get_user_by('ID', $id);
        
        if (!$user) {
            return $this->error([], 'User not found', 404);
        }
        
        return $this->success($user);
    }

    public function store(RouteRequest $request)
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
        $user_id = wp_create_user(
            $request->input('username'),
            $request->input('password'),
            $request->input('email')
        );
        
        if (is_wp_error($user_id)) {
            return $this->error([], $user_id->get_error_message(), 400);
        }
        
        return $this->success(['user_id' => $user_id], 'User created successfully', 201);
    }
}
```

## Integration with WordPress ORM

Perfect companion to WordPress ORM for database operations:

```php
use WordpressORM\Models\Post;

RouteManager::get('posts', function(RouteRequest $request) {
    $posts = Post::where('post_status', 'publish')
        ->where('post_type', 'post')
        ->orderBy('post_date', 'desc')
        ->limit(10)
        ->get();

    return $posts;
})->middleware('rate_limit:100,60');

RouteManager::post('posts', function(RouteRequest $request) {
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
    $post->post_author = $request->user()->ID;
    $post->save();

    return $post;
})->middleware(['auth', 'capability:edit_posts']);
```

## Configuration

### Set Default Namespace

```php
// Set your app's API namespace
RouteManager::setNamespace('myapp/v1');
```

### Global Middleware

```php
// Apply middleware to all routes
RouteManager::middleware(['cors', 'rate_limit:1000,60']);
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
$url = RouteManager::url('user_profile', ['id' => 123]);
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
RouteManager::post('sensitive', function(RouteRequest $request) {
    try {
        // Check authentication and permissions
        if (!$request->isAuthenticated()) {
            return new WP_Error('unauthorized', 'Authentication required', ['status' => 401]);
        }
        
        // Risky operation
        return performOperation($request->all());
    } catch (Exception $e) {
        // Log error internally
        error_log('API Error: ' . $e->getMessage());
        
        return new WP_Error(
            'operation_failed',
            WP_DEBUG ? $e->getMessage() : 'An error occurred',
            ['status' => 500]
        );
    }
})->middleware(['auth', 'rate_limit:10,60']);
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
RouteManager::setNamespace('myapp/v1');

// Public routes
RouteManager::get('users', 'UserController@index');
RouteManager::get('users/(?P<id>[\d]+)', 'UserController@show');

// Protected routes
RouteManager::group(['middleware' => 'auth'], function() {
    RouteManager::get('profile', 'UserController@profile');
    RouteManager::put('profile', 'UserController@updateProfile');
    RouteManager::post('avatar', 'UserController@uploadAvatar');
});

// Admin only routes
RouteManager::group([
    'middleware' => ['auth', 'capability:manage_users'],
    'prefix' => 'admin'
], function() {
    RouteManager::resource('users', 'Admin\UserController');
    RouteManager::post('users/(?P<id>[\d]+)/ban', 'Admin\UserController@ban');
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
