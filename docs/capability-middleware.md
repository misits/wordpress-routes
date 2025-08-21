# Capability Middleware

The CapabilityMiddleware provides fine-grained access control based on WordPress user capabilities. It ensures users have specific permissions before accessing protected routes.

## Basic Usage

### Simple Capability Check

```php
use WordPressRoutes\Routing\RouteManager;

// Require 'manage_options' capability (admin-only)
RouteManager::get('admin/settings', 'SettingsController@index')
    ->middleware('capability:manage_options');

// Require 'edit_posts' capability 
RouteManager::get('posts/drafts', 'PostController@drafts')
    ->middleware('capability:edit_posts');

// Require 'upload_files' capability
RouteManager::post('media/upload', 'MediaController@upload')
    ->middleware('capability:upload_files');
```

### Multiple Capabilities

```php
// User must have both authentication AND the capability
RouteManager::group(['middleware' => ['auth', 'capability:publish_posts']], function() {
    RouteManager::post('posts/publish', 'PostController@publish');
    RouteManager::put('posts/{id}/publish', 'PostController@publishPost');
});
```

### Different Capabilities for Different Routes

```php
RouteManager::group(['middleware' => 'auth'], function() {
    // Authors can edit their own posts
    RouteManager::get('posts/mine', 'PostController@mine')
        ->middleware('capability:edit_posts');
    
    // Editors can edit all posts
    RouteManager::get('posts/all', 'PostController@all')
        ->middleware('capability:edit_others_posts');
    
    // Only admins can delete posts
    RouteManager::delete('posts/{id}', 'PostController@delete')
        ->middleware('capability:delete_posts');
    
    // Only administrators can manage users
    RouteManager::resource('users', 'UserController')
        ->middleware('capability:manage_options');
});
```

## WordPress Capabilities Reference

### Standard User Capabilities

```php
// Basic capabilities
'read'                    // Can read content
'edit_posts'             // Can edit own posts
'edit_published_posts'   // Can edit own published posts
'edit_others_posts'      // Can edit others' posts
'publish_posts'          // Can publish posts
'delete_posts'           // Can delete own posts
'delete_published_posts' // Can delete own published posts
'delete_others_posts'    // Can delete others' posts

// Media capabilities
'upload_files'           // Can upload files
'edit_files'            // Can edit files directly

// Page capabilities  
'edit_pages'            // Can edit own pages
'edit_others_pages'     // Can edit others' pages
'publish_pages'         // Can publish pages
'delete_pages'          // Can delete own pages
'delete_others_pages'   // Can delete others' pages

// User management
'create_users'          // Can create new users
'delete_users'          // Can delete users
'list_users'            // Can list users
'edit_users'            // Can edit users
'promote_users'         // Can change user roles

// Admin capabilities
'manage_options'        // Can manage site options
'manage_categories'     // Can manage categories
'manage_links'          // Can manage links
'moderate_comments'     // Can moderate comments
'install_plugins'       // Can install plugins
'activate_plugins'      // Can activate plugins
'edit_plugins'          // Can edit plugins
'install_themes'        // Can install themes
'switch_themes'         // Can switch themes
'edit_themes'           // Can edit themes
```

### Custom Post Type Capabilities

```php
// For custom post type 'product'
'edit_products'         // Can edit own products
'edit_others_products'  // Can edit others' products
'publish_products'      // Can publish products
'read_private_products' // Can read private products
'delete_products'       // Can delete own products
'delete_others_products' // Can delete others' products
```

## Advanced Usage

### Object-Specific Capabilities

```php
// Check capability for specific post
RouteManager::put('posts/{id}', function(RouteRequest $request) {
    $postId = $request->param('id');
    
    // Check if user can edit this specific post
    if (!current_user_can('edit_post', $postId)) {
        return new WP_Error(
            'insufficient_permission',
            'You cannot edit this post',
            ['status' => 403]
        );
    }
    
    // Update the post
    // ...
});

// Or use middleware with object context
RouteManager::put('posts/{id}', 'PostController@update')
    ->middleware(['auth', function($request) {
        $postId = $request->param('id');
        return current_user_can('edit_post', $postId) 
            ? null 
            : new WP_Error('forbidden', 'Cannot edit this post', ['status' => 403]);
    }]);
```

### Role-Based Access Control

```php
// Check user roles
RouteManager::get('admin/dashboard', function($request) {
    $user = $request->user();
    
    // Check if user has admin or editor role
    if (!in_array('administrator', $user->roles) && !in_array('editor', $user->roles)) {
        return new WP_Error(
            'insufficient_role',
            'Admin or Editor role required',
            ['status' => 403]
        );
    }
    
    return ['dashboard' => 'data'];
});
```

### Custom Capability Checks

```php
// Create custom capability middleware
RouteManager::get('premium/content', function($request) {
    return ['premium' => 'content'];
})->middleware(['auth', function($request) {
    // Custom logic for premium access
    $user = $request->user();
    $hasPremium = get_user_meta($user->ID, 'has_premium_access', true);
    
    return $hasPremium 
        ? null 
        : new WP_Error('premium_required', 'Premium access required', ['status' => 403]);
}]);
```

## Real-World Examples

### Blog Management API

```php
RouteManager::setNamespace('blog/v1');

// Public endpoints
RouteManager::get('posts', 'PostController@index');
RouteManager::get('posts/{id}', 'PostController@show');

// Author endpoints
RouteManager::group(['middleware' => ['auth', 'capability:edit_posts']], function() {
    RouteManager::get('posts/mine', 'PostController@myPosts');
    RouteManager::post('posts', 'PostController@create');
    RouteManager::put('posts/{id}', 'PostController@update');
});

// Editor endpoints
RouteManager::group(['middleware' => ['auth', 'capability:edit_others_posts']], function() {
    RouteManager::get('posts/all', 'PostController@all');
    RouteManager::put('posts/{id}/status', 'PostController@changeStatus');
});

// Admin endpoints
RouteManager::group(['middleware' => ['auth', 'capability:manage_options']], function() {
    RouteManager::delete('posts/{id}', 'PostController@delete');
    RouteManager::get('posts/trash', 'PostController@trash');
    RouteManager::post('posts/{id}/restore', 'PostController@restore');
});
```

### E-commerce API

```php
RouteManager::setNamespace('shop/v1');

// Customer endpoints
RouteManager::group(['middleware' => 'auth'], function() {
    RouteManager::get('orders/mine', 'OrderController@myOrders');
    RouteManager::post('orders', 'OrderController@create');
});

// Shop manager endpoints
RouteManager::group(['middleware' => ['auth', 'capability:edit_products']], function() {
    RouteManager::resource('products', 'ProductController');
    RouteManager::get('orders', 'OrderController@index');
    RouteManager::put('orders/{id}/status', 'OrderController@updateStatus');
});

// Administrator endpoints
RouteManager::group(['middleware' => ['auth', 'capability:manage_woocommerce']], function() {
    RouteManager::get('reports/sales', 'ReportController@sales');
    RouteManager::get('settings', 'SettingsController@index');
    RouteManager::put('settings', 'SettingsController@update');
});
```

### User Management API

```php
RouteManager::setNamespace('users/v1');

// Self-management (all authenticated users)
RouteManager::group(['middleware' => 'auth'], function() {
    RouteManager::get('profile', 'UserController@profile');
    RouteManager::put('profile', 'UserController@updateProfile');
    RouteManager::post('avatar', 'UserController@uploadAvatar');
});

// User listing (requires list_users capability)
RouteManager::get('users', 'UserController@index')
    ->middleware(['auth', 'capability:list_users']);

// User management (requires edit_users capability)
RouteManager::group(['middleware' => ['auth', 'capability:edit_users']], function() {
    RouteManager::get('users/{id}', 'UserController@show');
    RouteManager::put('users/{id}', 'UserController@update');
    RouteManager::put('users/{id}/role', 'UserController@changeRole');
});

// User creation/deletion (requires create_users/delete_users)
RouteManager::post('users', 'UserController@create')
    ->middleware(['auth', 'capability:create_users']);

RouteManager::delete('users/{id}', 'UserController@delete')
    ->middleware(['auth', 'capability:delete_users']);
```

## Error Responses

The CapabilityMiddleware returns standardized error responses:

### Unauthenticated User

```json
{
    "code": "unauthenticated",
    "message": "Authentication required",
    "data": {
        "status": 401
    }
}
```

### Insufficient Capability

```json
{
    "code": "insufficient_capability",
    "message": "You need the \"manage_options\" capability to access this resource",
    "data": {
        "status": 403
    }
}
```

### Object-Specific Permission Denied

```json
{
    "code": "insufficient_capability",
    "message": "You do not have permission to edit_post this resource",
    "data": {
        "status": 403
    }
}
```

## Best Practices

1. **Always combine with auth middleware**: Capability checks require authentication
2. **Use appropriate capabilities**: Match capabilities to the actual action being performed
3. **Check object-specific permissions**: For post/user/media editing, check permissions on the specific object
4. **Provide clear error messages**: Help developers understand why access was denied
5. **Use capability groups**: Group routes with similar permission requirements
6. **Document required capabilities**: Make it clear what permissions are needed for each endpoint

## Custom Capabilities

You can create custom capabilities for your application:

```php
// Add custom capability to role
$role = get_role('editor');
$role->add_cap('manage_products');

// Use in routes
RouteManager::resource('products', 'ProductController')
    ->middleware(['auth', 'capability:manage_products']);

// Remove capability
$role->remove_cap('manage_products');
```

## Testing Capability Middleware

```php
// Test with different user roles
public function testCapabilityMiddleware()
{
    // Test as subscriber (should fail)
    wp_set_current_user($this->subscriber_id);
    $response = rest_do_request('/wp-json/blog/v1/admin/settings');
    $this->assertEquals(403, $response->get_status());
    
    // Test as administrator (should pass)
    wp_set_current_user($this->admin_id);
    $response = rest_do_request('/wp-json/blog/v1/admin/settings');
    $this->assertEquals(200, $response->get_status());
}
```

The CapabilityMiddleware provides a powerful and flexible way to secure your WordPress Routes based on user capabilities, ensuring proper access control throughout your API.