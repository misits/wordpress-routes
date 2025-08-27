# Modern Unified Routing System

WordPress Routes provides a unified routing system that handles **API, Web, Admin, AJAX, and Webhook routes** with consistent Laravel-style syntax and powerful features.

## Quick Start

### Organized Routes Structure

The modern routing system uses organized route files in a `routes/` directory:

```
your-theme/
├── routes/
│   ├── api.php      # REST API endpoints
│   ├── web.php      # Frontend pages
│   ├── auth.php     # Protected routes
│   ├── webhooks.php # External integrations
│   └── admin.php    # WordPress admin pages
```

### Basic Route Examples

```php
<?php
use WordPressRoutes\Routing\Route;
use WordPressRoutes\Routing\RouteManager;

// API Routes (in routes/api.php)
RouteManager::setNamespace('myapp/v1');

Route::get('users/{id}', function($request) {
    return ['user' => get_user_by('id', $request->param('id'))];
});

// Web Routes (in routes/web.php)
Route::web('about/{section}', function($request) {
    $section = $request->param('section');
    return view('about-' . $section, ['section' => $section]);
})->title('About Us');

// Protected Routes (in routes/auth.php)
Route::web('dashboard/{tab}', function($request) {
    $tab = $request->param('tab');
    return view('dashboard', ['active_tab' => $tab]);
})->middleware(['auth'])->title('Dashboard');

// Webhook Routes (in routes/webhooks.php)
Route::webhook('github-deploy', function($request) {
    $payload = $request['json'];
    // Handle deployment
    return ['status' => 'deployed'];
})->signature('your-secret');
```

## Route Types

### 1. API Routes
REST API endpoints with automatic parameter extraction:
```php
// GET /wp-json/myapp/v1/posts/123/comments/456
Route::get('posts/{post_id}/comments/{comment_id}', function($request) {
    return [
        'post_id' => $request->param('post_id'),
        'comment_id' => $request->param('comment_id'),
        'data' => $request->all()
    ];
});
```

### 2. Web Routes
Frontend pages with template integration:
```php
// Visit: /products/electronics/laptop
Route::web('products/{category}/{slug}', function($request) {
    $product = get_product($request->param('slug'));
    return view('product-detail', ['product' => $product]);
})->title('Product Details');
```

### 3. Protected Routes
Authentication and capability-based routes:
```php
// Requires user to be logged in
Route::web('profile/{section}', $callback)->middleware(['auth']);

// Requires admin capabilities
Route::web('admin/{action}', $callback)->middleware(['capability:manage_options']);
```

### 4. AJAX Routes
WordPress AJAX integration:
```php
Route::ajax('load_more_posts', function() {
    $posts = get_posts(['offset' => $_POST['offset']]);
    wp_send_json_success($posts);
})->public();
```

### 5. Webhook Routes
External service integrations:
```php
Route::webhook('stripe/payment', function($request) {
    $event = $request['json'];
    handle_payment_event($event);
    return ['received' => true];
})->signature(env('STRIPE_WEBHOOK_SECRET'));
```

## Universal Parameter System

All route types support parameter extraction with the same syntax:

```php
// URL: /api/users/123/posts/456?status=published
Route::get('users/{user_id}/posts/{post_id}', function($request) {
    return [
        'user_id' => $request->param('user_id'),      // 123
        'post_id' => $request->param('post_id'),      // 456
        'status' => $request->query('status'),         // published
        'all_params' => $request->params(),            // ['user_id' => 123, 'post_id' => 456]
        'all_input' => $request->all()                 // All data combined
    ];
});
```

## Controllers

Use controllers for organized code:

```php
// routes/web.php
Route::web('products/{slug}', 'ProductController@show');
Route::web('categories/{category}/products', 'ProductController@index');

// controllers/ProductController.php
class ProductController {
    public function show($request) {
        $slug = $request->param('slug');
        $product = Product::find_by_slug($slug);
        return view('product-detail', ['product' => $product]);
    }
    
    public function index($request) {
        $category = $request->param('category');
        $products = Product::by_category($category);
        return view('products', ['products' => $products]);
    }
}
```

## Middleware System

Protect routes with middleware:

```php
// Individual middleware
Route::web('dashboard', $callback)->middleware(['auth', 'capability:edit_posts']);

// Route groups
Route::group(['middleware' => ['auth']], function() {
    Route::web('profile', $profileCallback);
    Route::web('settings', $settingsCallback);
    Route::api('user-data', $userDataCallback);
});
```

## Template Integration

Web routes integrate with WordPress themes:

```php
// Return view with data
Route::web('portfolio/{project}', function($request) {
    return view('portfolio-detail', [
        'project' => $request->param('project'),
        'featured' => true
    ]);
});

// Use custom template
Route::web('custom-page', function($request) {
    return $request->query();
})->template('custom-template.php');
```

## File Organization

### Automatic Loading
Route files are automatically loaded in priority order:
1. `routes/api.php` - API endpoints (highest priority)
2. `routes/auth.php` - Protected routes  
3. `routes/web.php` - Public web routes (lowest priority)
4. `routes/webhooks.php` - Webhook endpoints
5. `routes/admin.php` - Admin pages

### Manual Loading
You can also manually load routes:
```php
// functions.php
require_once get_template_directory() . '/routes/custom.php';
```

## Advanced Features

### Route Caching
Routes are automatically cached for performance.

### Route Groups
Organize related routes:
```php
Route::group(['prefix' => 'api/v2', 'middleware' => ['auth']], function() {
    Route::get('users', $callback);
    Route::get('posts', $callback);
});
```

### Route Names
Name routes for URL generation:
```php
Route::web('contact', $callback)->name('contact.show');
$url = route_url('contact.show'); // Get URL
```

### Multiple Parameters
Handle complex URLs:
```php
// /shop/electronics/laptops/gaming?color=black&price=1000
Route::web('shop/{category}/{subcategory}/{type}', function($request) {
    return [
        'category' => $request->param('category'),
        'subcategory' => $request->param('subcategory'), 
        'type' => $request->param('type'),
        'filters' => $request->query()
    ];
});
```

## Migration from Legacy

If migrating from the old API-only system:

### Old Way (Legacy)
```php
// Old routes.php
Route::setNamespace('api/v1');
Route::get('users', $callback); // API only
```

### New Way (Modern)
```php
// routes/api.php
RouteManager::setNamespace('api/v1');
Route::get('users', $callback); // API routes

// routes/web.php  
Route::web('users/{id}', $callback); // Web routes

// routes/auth.php
Route::web('dashboard', $callback)->middleware(['auth']); // Protected
```

The modern system provides the same API route functionality plus support for all other route types with a unified interface.

## Next Steps

- See [Controllers Documentation](controllers.md) for controller usage
- See [Middleware Documentation](middleware.md) for authentication and security  
- See [Template System](templates.md) for view rendering
- See [Examples](examples.md) for complete implementation examples

The unified routing system gives you the power of Laravel-style routing within WordPress, supporting all route types with consistent parameter handling and powerful features.