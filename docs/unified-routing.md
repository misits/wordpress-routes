# Unified Routing System

WordPress Routes provides a unified `Route` class that handles **API, Web, Admin, and AJAX routes** with a consistent API and powerful features.

## Table of Contents

- [Route Types](#route-types)
- [API Routes](#api-routes)
- [Web Routes](#web-routes) 
- [Admin Routes](#admin-routes)
- [AJAX Routes](#ajax-routes)
- [Template System](#template-system)
- [Middleware](#middleware)
- [Plugin vs Theme Mode](#plugin-vs-theme-mode)

## Route Types

The unified `Route` class provides four distinct route types:

```php
use WordPressRoutes\Routing\Route;

// API Routes - REST API endpoints
Route::get('users', $callback);
Route::post('contact', $callback);

// Web Routes - Frontend pages with templates  
Route::web('about', $callback)->template('about.php');

// Admin Routes - Dashboard pages
Route::admin('settings', 'Settings Page', $callback)->template('admin-settings.php');

// AJAX Routes - WordPress AJAX handlers
Route::ajax('save_data', $callback)->auth();
```

## API Routes

Traditional REST API endpoints that integrate with WordPress REST API:

### Basic Usage
```php
// Simple GET endpoint
Route::get('posts', function($request) {
    return get_posts();
});

// POST with data processing
Route::post('contact', function($request) {
    $data = $request->validated();
    wp_mail($data['email'], 'Contact Form', $data['message']);
    return ['sent' => true];
})->validate(['email' => 'required|email', 'message' => 'required']);

// Route with parameters
Route::get('posts/{id}', function($request) {
    $id = $request->param('id');
    return get_post($id);
});
```

### API Route Methods
```php
Route::get($endpoint, $callback)       // GET request
Route::post($endpoint, $callback)      // POST request  
Route::put($endpoint, $callback)       // PUT request
Route::patch($endpoint, $callback)     // PATCH request
Route::delete($endpoint, $callback)    // DELETE request
Route::any($endpoint, $callback)       // Any HTTP method
```

### API Route Features
- WordPress REST API integration
- Automatic JSON responses
- Parameter extraction
- Middleware support
- Validation
- Rate limiting

## Web Routes

Frontend pages with custom templates and WordPress integration:

### Basic Usage
```php
Route::web('about', function($request) {
    return [
        'company' => 'My Company',
        'team' => get_users(['role' => 'author']),
        'projects' => get_posts(['post_type' => 'project'])
    ];
})->template('about.php')->title('About Us');
```

### Web Route Features
- Custom templates with data binding
- WordPress page integration
- SEO-friendly URLs
- Title customization
- Middleware support

### Template Data Access
In your template file:
```php
<?php
// Method 1: Global variable
$data = $GLOBALS['route_data'] ?? [];
$company = $data['company'] ?? '';

// Method 2: Extracted variables (available directly)
echo $company; // Available if returned from route callback
?>
```

### Web Route Methods
```php
Route::web($path, $callback)
    ->template($template)              // Set template file
    ->title($title)                    // Set page title
    ->middleware($middleware)          // Add middleware
```

## Admin Routes

WordPress admin dashboard pages with full template support:

### Basic Usage
```php
Route::admin('analytics', 'Analytics Dashboard', function($request) {
    return [
        'stats' => get_analytics_data(),
        'users' => count(get_users()),
        'posts' => wp_count_posts()->publish
    ];
})
->template('admin-analytics.php')
->icon('dashicons-chart-bar')
->position(25)
->capability('manage_options');
```

### Admin Route Features
- Custom admin templates
- WordPress admin integration
- Menu customization (icon, position)
- Capability-based access control
- Data binding to templates
- Submenu support

### Admin Template Data Access
In your admin template:
```php
<?php
$data = $GLOBALS['admin_route_data'] ?? [];
$request = $GLOBALS['admin_route_request'] ?? [];
?>
<div class="wrap">
    <h1><?php echo esc_html($data['title'] ?? 'Admin Page'); ?></h1>
    <p>Total users: <?php echo intval($data['users']); ?></p>
</div>
```

### Admin Route Methods
```php
Route::admin($slug, $page_title, $callback)
    ->template($template)              // Custom admin template
    ->capability($capability)          // Required capability
    ->icon($icon)                     // Menu icon (dashicons)
    ->position($position)             // Menu position
    ->parent($parent)                 // Parent menu (for submenus)
    ->menu($menu_title)               // Custom menu title
```

### Admin Route Examples
```php
// Main admin page
Route::admin('my-plugin', 'My Plugin Settings', $callback)
    ->template('admin-settings.php')
    ->icon('dashicons-admin-plugins')
    ->position(30);

// Submenu under existing menu
Route::admin('my-tools', 'My Tools', $callback)
    ->template('admin-tools.php')
    ->parent('tools.php');

// Custom capability
Route::admin('advanced', 'Advanced Settings', $callback)
    ->capability('edit_plugins')
    ->template('admin-advanced.php');
```

## AJAX Routes

WordPress AJAX handlers for both authenticated and public requests:

### Basic Usage
```php
// Authenticated AJAX
Route::ajax('save_settings', function($request) {
    update_option('my_setting', $request->input('value'));
    return ['saved' => true];
})->auth();

// Public AJAX (no authentication required)
Route::ajax('get_public_data', function($request) {
    return ['data' => get_public_data()];
})->public();
```

### AJAX Route Features
- WordPress AJAX integration
- Authentication control
- Nonce verification
- JSON responses
- Middleware support

### AJAX Route Methods
```php
Route::ajax($action, $callback)
    ->auth()                          // Require authentication
    ->public()                        // Allow public access
    ->nopriv($allow)                 // Control nopriv access
    ->middleware($middleware)         // Add middleware
```

### Frontend AJAX Usage
```javascript
// Authenticated AJAX
$.post(ajaxurl, {
    action: 'save_settings',
    value: 'new_value',
    _wpnonce: my_nonce
}, function(response) {
    console.log(response.saved);
});

// Public AJAX
$.post(ajaxurl, {
    action: 'get_public_data'
}, function(response) {
    console.log(response.data);
});
```

## Template System

### Template Resolution

Templates are resolved based on `WPROUTES_MODE`:

#### Theme Mode (Default)
```php
// Template resolution order:
// 1. child-theme/template.php
// 2. parent-theme/template.php  
// 3. WordPress locate_template() standard

->template('about.php')               // Uses theme/about.php
->template('pages/portfolio.php')    // Uses theme/pages/portfolio.php
```

#### Plugin Mode
```php
define('WPROUTES_MODE', 'plugin');

// Web routes search:
// 1. plugin/templates/template.php
// 2. plugin/views/template.php
// 3. plugin/template.php
// 4. Fallback to theme

// Admin routes search:
// 1. plugin/admin-templates/template.php
// 2. plugin/templates/admin/template.php
// 3. plugin/templates/template.php
// 4. plugin/views/admin/template.php
// 5. plugin/template.php
// 6. Fallback to theme
```

### Template Path Examples

```php
// Simple filenames
->template('about.php')              // Searches in template directories

// Relative paths
->template('admin/settings.php')     // Relative to theme/plugin root
->template('templates/page.php')     // Custom subfolder

// Absolute paths  
->template('/custom/path/file.php')  // Direct absolute path
```

## Middleware

All route types support middleware for authentication, validation, and custom logic:

### Built-in Middleware
```php
// Authentication
->middleware('auth')                 // Require logged-in user

// Capabilities
->middleware('can:manage_options')   // Require specific capability

// Rate limiting
->middleware('rate_limit:10,60')     // 10 requests per minute

// CORS
->cors()                            // Enable CORS headers

// Validation
->validate(['email' => 'required|email'])
```

### Custom Middleware
```php
->middleware(function($request) {
    if (!custom_check($request)) {
        return false; // Deny access
    }
    return true; // Allow access
})
```

### Multiple Middleware
```php
->middleware(['auth', 'can:edit_posts', 'rate_limit:30,60'])
```

## Plugin vs Theme Mode

Configure the mode based on your deployment:

### Theme Mode (Default)
```php
// In theme's functions.php
define('WPROUTES_MODE', 'theme');
require_once get_template_directory() . '/lib/wp-routes/bootstrap.php';

// Create: theme/routes.php
Route::web('about', $callback)->template('about.php');
Route::admin('settings', 'Settings', $callback)->template('admin-settings.php');
```

### Plugin Mode
```php
// In plugin file
define('WPROUTES_MODE', 'plugin'); 
require_once plugin_dir_path(__FILE__) . 'lib/wp-routes/bootstrap.php';

// Create: plugin/routes.php
Route::web('about', $callback)->template('about.php'); // Uses plugin/templates/about.php
Route::admin('settings', 'Settings', $callback)->template('settings.php'); // Uses plugin/admin-templates/settings.php
```

## Advanced Examples

### Complete Web Route with Middleware
```php
Route::web('portfolio/{category?}', function($request) {
    $category = $request->param('category', 'all');
    
    return [
        'title' => ucfirst($category) . ' Portfolio',
        'projects' => get_portfolio_projects($category),
        'categories' => get_portfolio_categories()
    ];
})
->template('portfolio.php')
->title(function($request) {
    $category = $request->param('category', 'all');
    return ucfirst($category) . ' Portfolio - My Site';
})
->middleware(['cache:3600']); // Custom cache middleware
```

### Complete Admin Route with Form Handling
```php
Route::admin('email-settings', 'Email Settings', function($request) {
    if ($request['post']) {
        // Handle form submission
        update_option('email_from', sanitize_email($request['post']['email_from']));
        update_option('email_name', sanitize_text_field($request['post']['email_name']));
        
        return [
            'title' => 'Email Settings',
            'message' => 'Settings saved successfully!',
            'email_from' => get_option('email_from'),
            'email_name' => get_option('email_name')
        ];
    }
    
    return [
        'title' => 'Email Settings',
        'email_from' => get_option('email_from', get_option('admin_email')),
        'email_name' => get_option('email_name', get_option('blogname'))
    ];
})
->template('admin-email-settings.php')
->capability('manage_options')
->icon('dashicons-email')
->position(80);
```

### API Route with Full Validation
```php
Route::post('contact', function($request) {
    $data = $request->validated();
    
    // Send email
    $sent = wp_mail(
        get_option('admin_email'),
        'Contact Form: ' . $data['subject'],
        $data['message'],
        ['From: ' . $data['name'] . ' <' . $data['email'] . '>']
    );
    
    return [
        'sent' => $sent,
        'message' => $sent ? 'Message sent successfully!' : 'Failed to send message'
    ];
})
->validate([
    'name' => 'required|min:2',
    'email' => 'required|email', 
    'subject' => 'required|max:100',
    'message' => 'required|min:10'
])
->middleware(['rate_limit:5,60']) // 5 submissions per hour
->cors();
```

This unified system provides incredible flexibility while maintaining simplicity and WordPress integration.