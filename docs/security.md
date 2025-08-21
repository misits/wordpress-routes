# Security

WordPress Routes implements multiple layers of security to protect your API endpoints. This guide covers security features, best practices, and implementation patterns.

## Built-in Security Features

### 1. Direct Access Protection

All WordPress Routes files include direct access protection:

```php
defined('ABSPATH') or exit();
```

### 2. Input Validation

WordPress Routes provides comprehensive input validation:

```php
use WordPressRoutes\Routing\RouteRequest;

public function store(RouteRequest $request)
{
    $validation = $request->validate([
        'email' => 'required|email',
        'password' => 'required|min:8',
        'age' => 'numeric|min:18|max:120',
        'website' => 'url',
        'username' => 'required|min:3|max:20'
    ]);
    
    if (is_wp_error($validation)) {
        return $validation; // Returns 422 with validation errors
    }
    
    // Safe to use validated data
    $email = $request->input('email');
}
```

### 3. Data Sanitization

The BaseController provides sanitization methods:

```php
use WordPressRoutes\Routing\BaseController;

class UserController extends BaseController
{
    public function update(RouteRequest $request)
    {
        // Sanitize input data
        $data = $this->sanitize($request->all(), [
            'email' => 'email',
            'name' => 'text',
            'bio' => 'textarea',
            'website' => 'url',
            'age' => 'int',
            'price' => 'float'
        ]);
        
        // Data is now sanitized and safe to use
        update_user_meta($user_id, 'bio', $data['bio']);
    }
}
```

### 4. CSRF Protection

WordPress nonces provide CSRF protection:

```php
// Generate nonce for form
$nonce = wp_create_nonce('my_action');

// Verify nonce in API endpoint
public function deleteItem(RouteRequest $request)
{
    $nonce = $request->header('X-WP-Nonce') ?: $request->query('_wpnonce');
    
    if (!wp_verify_nonce($nonce, 'delete_item_' . $request->param('id'))) {
        return new WP_Error('invalid_nonce', 'Security check failed', ['status' => 403]);
    }
    
    // Safe to proceed with deletion
}
```

### 5. SQL Injection Prevention

WordPress Routes uses WordPress's database APIs which automatically prevent SQL injection:

```php
// Safe: Uses WordPress prepared statements internally
$posts = Post::where('status', $request->input('status'))
    ->where('author', $request->input('author_id'))
    ->get();

// Safe: WordPress functions handle escaping
$user = get_user_by('email', $request->input('email'));

// For custom queries, use wpdb prepare
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
        $request->input('author_id'),
        $request->input('status')
    )
);
```

### 6. XSS Prevention

Always escape output when returning HTML content:

```php
public function getContent(RouteRequest $request)
{
    $content = get_post_field('post_content', $request->param('id'));
    
    return $this->success([
        'content' => wp_kses_post($content), // Allow only safe HTML
        'title' => esc_html(get_the_title($request->param('id'))),
        'url' => esc_url(get_permalink($request->param('id')))
    ]);
}
```

## Authentication & Authorization

### Middleware-Based Protection

```php
// Single middleware
RouteManager::get('admin/users', 'AdminController@users')
    ->middleware('auth');

// Multiple middleware layers
RouteManager::delete('posts/{id}', 'PostController@destroy')
    ->middleware(['auth', 'capability:delete_posts', 'rate_limit:10,1']);

// Group protection
RouteManager::group(['middleware' => ['auth', 'capability:manage_options']], function() {
    // All routes in this group require authentication and admin capability
    RouteManager::resource('settings', 'SettingsController');
    RouteManager::post('cache/clear', 'CacheController@clear');
});
```

### Capability Checking

```php
class PostController extends BaseController
{
    public function update(RouteRequest $request)
    {
        $post_id = $request->param('id');
        
        // Check specific capability for this post
        if (!current_user_can('edit_post', $post_id)) {
            return $this->forbidden('You cannot edit this post');
        }
        
        // Check post author
        $post = get_post($post_id);
        if ($post->post_author != get_current_user_id() && !current_user_can('edit_others_posts')) {
            return $this->forbidden('You can only edit your own posts');
        }
        
        // Safe to proceed
    }
}
```

## Rate Limiting

Prevent abuse with rate limiting middleware:

```php
// Basic rate limiting
RouteManager::post('contact', 'ContactController@send')
    ->middleware('rate_limit:5,60'); // 5 requests per 60 minutes

// Different limits for different endpoints
RouteManager::get('search', 'SearchController@search')
    ->middleware('rate_limit:30,1'); // 30 per minute

RouteManager::post('upload', 'UploadController@store')
    ->middleware(['auth', 'rate_limit:10,60']); // 10 per hour for authenticated users

// Custom rate limiting
class CustomRateLimiter implements MiddlewareInterface
{
    public function handle(RouteRequest $request)
    {
        $identifier = $request->isAuthenticated() 
            ? 'user_' . get_current_user_id()
            : 'ip_' . $_SERVER['REMOTE_ADDR'];
            
        $key = 'rate_limit_' . $identifier . '_' . $request->path();
        $attempts = get_transient($key) ?: 0;
        
        if ($attempts >= 100) {
            return new WP_Error(
                'rate_limit_exceeded',
                'Too many requests',
                ['status' => 429]
            );
        }
        
        set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
        return null;
    }
}
```

## Input Validation Rules

### Available Validation Rules

```php
$rules = [
    // Type validation
    'name' => 'required|string',
    'age' => 'required|numeric',
    'price' => 'required|numeric',
    'is_active' => 'boolean',
    
    // String validation
    'username' => 'required|min:3|max:20',
    'password' => 'required|min:8',
    'description' => 'max:500',
    
    // Format validation
    'email' => 'required|email',
    'website' => 'url',
    'phone' => 'regex:/^[0-9]{10}$/',
    
    // Numeric validation
    'quantity' => 'integer|min:1|max:100',
    'rating' => 'numeric|min:0|max:5',
    
    // Date validation
    'start_date' => 'required|date',
    'end_date' => 'required|date|after:start_date',
    
    // File validation
    'avatar' => 'file|max:2048|mimes:jpg,png',
    
    // Custom validation
    'status' => 'required|in:draft,published,archived',
    'role' => 'required|in:admin,editor,author,subscriber'
];
```

### Custom Validation

```php
public function store(RouteRequest $request)
{
    // Basic validation
    $validation = $request->validate([
        'email' => 'required|email',
        'username' => 'required|min:3'
    ]);
    
    if (is_wp_error($validation)) {
        return $validation;
    }
    
    // Custom validation
    if (username_exists($request->input('username'))) {
        return new WP_Error(
            'username_taken',
            'This username is already taken',
            ['status' => 422]
        );
    }
    
    if (email_exists($request->input('email'))) {
        return new WP_Error(
            'email_exists',
            'This email is already registered',
            ['status' => 422]
        );
    }
    
    // Proceed with creation
}
```

## Sanitization Functions

### Built-in WordPress Sanitization

```php
class DataController extends BaseController
{
    public function process(RouteRequest $request)
    {
        // Text sanitization
        $title = sanitize_text_field($request->input('title'));
        $content = sanitize_textarea_field($request->input('content'));
        $slug = sanitize_title($request->input('slug'));
        
        // HTML sanitization
        $bio = wp_kses_post($request->input('bio')); // Allow safe HTML
        $description = wp_strip_all_tags($request->input('description')); // Remove all HTML
        
        // Email and URL
        $email = sanitize_email($request->input('email'));
        $website = esc_url_raw($request->input('website'));
        
        // File name
        $filename = sanitize_file_name($request->input('filename'));
        
        // SQL sanitization (for direct queries)
        global $wpdb;
        $safe_value = esc_sql($request->input('search_term'));
        
        // Key sanitization
        $meta_key = sanitize_key($request->input('meta_key'));
        
        // Numeric sanitization
        $page = absint($request->query('page', 1));
        $limit = intval($request->query('limit', 10));
        $price = floatval($request->input('price'));
    }
}
```

### Custom Sanitization Helper

```php
protected function sanitizeInput($data, $type = 'text')
{
    switch ($type) {
        case 'email':
            return sanitize_email($data);
        case 'url':
            return esc_url_raw($data);
        case 'html':
            return wp_kses_post($data);
        case 'textarea':
            return sanitize_textarea_field($data);
        case 'int':
            return intval($data);
        case 'float':
            return floatval($data);
        case 'bool':
            return filter_var($data, FILTER_VALIDATE_BOOLEAN);
        case 'slug':
            return sanitize_title($data);
        case 'text':
        default:
            return sanitize_text_field($data);
    }
}
```

## File Upload Security

```php
class UploadController extends BaseController
{
    public function store(RouteRequest $request)
    {
        if (!$request->hasFile('file')) {
            return $this->error('No file uploaded', 'missing_file', 400);
        }
        
        $file = $request->file('file');
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file_type['type'], $allowed_types)) {
            return $this->error('Invalid file type', 'invalid_type', 422);
        }
        
        // Validate file size (2MB limit)
        if ($file['size'] > 2 * 1024 * 1024) {
            return $this->error('File too large', 'file_too_large', 422);
        }
        
        // Use WordPress upload handler for security
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $upload = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            ]
        ]);
        
        if (isset($upload['error'])) {
            return $this->error($upload['error'], 'upload_failed', 500);
        }
        
        // Create attachment
        $attachment_id = wp_insert_attachment([
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name($file['name']),
            'post_content' => '',
            'post_status' => 'inherit'
        ], $upload['file']);
        
        // Generate metadata
        wp_update_attachment_metadata(
            $attachment_id,
            wp_generate_attachment_metadata($attachment_id, $upload['file'])
        );
        
        return $this->success([
            'id' => $attachment_id,
            'url' => $upload['url']
        ]);
    }
}
```

## Security Headers

```php
// Add security headers to API responses
add_action('rest_api_init', function() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    if (is_ssl()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
});
```

## Error Handling

Never expose sensitive information in error messages:

```php
public function process(RouteRequest $request)
{
    try {
        // Process request
        $result = $this->performAction($request->all());
        return $this->success($result);
        
    } catch (Exception $e) {
        // Log detailed error internally
        error_log('API Error: ' . $e->getMessage() . ' in ' . $e->getFile());
        
        // Return generic error to user
        if (WP_DEBUG) {
            // Only show details in debug mode
            return $this->error($e->getMessage(), 'processing_error', 500);
        } else {
            // Generic error in production
            return $this->error('An error occurred processing your request', 'processing_error', 500);
        }
    }
}
```

## Security Checklist

### Development Phase
- [ ] Enable WP_DEBUG to catch issues early
- [ ] Validate all input data
- [ ] Sanitize data before storage
- [ ] Escape output when rendering
- [ ] Implement proper authentication
- [ ] Add rate limiting to prevent abuse
- [ ] Test with different user roles
- [ ] Check error messages don't leak info

### Before Production
- [ ] Disable WP_DEBUG
- [ ] Enable HTTPS/SSL
- [ ] Review all endpoints for authentication
- [ ] Implement rate limiting on all endpoints
- [ ] Add monitoring/logging
- [ ] Test CORS settings
- [ ] Review file upload restrictions
- [ ] Perform security audit

### Production Monitoring
- [ ] Monitor rate limit violations
- [ ] Track authentication failures
- [ ] Log suspicious activities
- [ ] Regular security updates
- [ ] Review access logs
- [ ] Monitor for unusual patterns

## Common Security Mistakes to Avoid

### 1. Trusting User Input

```php
// ❌ BAD: Direct use of user input
$user_id = $_GET['user_id'];
$user = get_user_by('id', $user_id);

// ✅ GOOD: Validate and sanitize
$user_id = absint($request->query('user_id'));
if (!$user_id) {
    return $this->error('Invalid user ID', 'invalid_id', 400);
}
$user = get_user_by('id', $user_id);
```

### 2. Insufficient Authorization

```php
// ❌ BAD: Only checking authentication
if ($request->isAuthenticated()) {
    // Delete any post
    wp_delete_post($request->param('id'));
}

// ✅ GOOD: Check specific capability
if (!current_user_can('delete_post', $request->param('id'))) {
    return $this->forbidden();
}
wp_delete_post($request->param('id'));
```

### 3. Exposing Sensitive Data

```php
// ❌ BAD: Returning all user data
return get_users();

// ✅ GOOD: Return only necessary fields
$users = get_users();
return array_map(function($user) {
    return [
        'id' => $user->ID,
        'name' => $user->display_name,
        'avatar' => get_avatar_url($user->ID)
    ];
}, $users);
```

### 4. Missing Rate Limiting

```php
// ❌ BAD: No rate limiting on expensive operation
RouteManager::get('search', function($request) {
    return perform_expensive_search($request->query('q'));
});

// ✅ GOOD: Add rate limiting
RouteManager::get('search', function($request) {
    return perform_expensive_search($request->query('q'));
})->middleware('rate_limit:10,1');
```

## Next Steps

- [Authentication Guide →](authentication.md)
- [Middleware Documentation →](middleware.md)
- [Best Practices →](best-practices.md)