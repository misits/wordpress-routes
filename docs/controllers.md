# Controllers

Controllers handle HTTP requests and return responses for your WordPress API. WordPress Routes provides powerful controller features with automatic discovery and generation.

## Creating Controllers

### Using CLI (Recommended)

Generate controllers using WP-CLI:

```bash
# Basic controller
wp wproutes make:controller ProductController

# API controller (extends BaseController)
wp wproutes make:controller ProductController --api

# Resource controller with CRUD methods
wp wproutes make:controller ProductController --resource

# API + Resource controller
wp wproutes make:controller ProductController --api --resource

# Nested controller
wp wproutes make:controller Admin/UserController --api

# Custom namespace
wp wproutes make:controller ProductController --namespace="MyApp\\Controllers"
```

### Manual Creation

Create a controller manually:

```php
<?php
// controllers/ProductController.php

use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;
use WP_REST_Response;

class ProductController extends BaseController
{
    public function index(WP_REST_Request $request)
    {
        // Your logic here
        $products = []; // Get products
        
        return $this->success($products);
    }
}
```

## Controller Types

### Basic Controller

Simple controller without inheritance:

```php
<?php
class BasicController
{
    public function handle()
    {
        return "Hello from controller";
    }
}
```

### API Controller

Extends BaseController with helper methods:

```php
<?php
use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;
use WP_REST_Response;

class ApiController extends BaseController
{
    public function index(WP_REST_Request $request)
    {
        $data = ['message' => 'Hello API'];
        return $this->success($data);
    }
    
    public function create(WP_REST_Request $request)
    {
        // Validate input
        $validation = $this->validate($request, [
            'name' => 'required|string|max:255',
            'email' => 'required|email'
        ]);
        
        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }
        
        // Create resource
        return $this->success([], 'Created successfully', 201);
    }
}
```

### Resource Controller

Full CRUD controller:

```php
<?php
use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;
use WP_REST_Response;

class ProductController extends BaseController
{
    /**
     * Display a listing of products
     */
    public function index(WP_REST_Request $request)
    {
        // Get all products
        $products = $this->getProducts($request);
        
        return $this->success($products);
    }

    /**
     * Store a newly created product
     */
    public function store(WP_REST_Request $request)
    {
        // Validation
        $rules = [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'string'
        ];
        
        $validation = $this->validate($request, $rules);
        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }
        
        // Create product
        $product = $this->createProduct($request->get_params());
        
        return $this->success($product, 'Product created successfully', 201);
    }

    /**
     * Display the specified product
     */
    public function show(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $product = $this->getProduct($id);
        
        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }
        
        return $this->success($product);
    }

    /**
     * Update the specified product
     */
    public function update(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        
        // Check if product exists
        $product = $this->getProduct($id);
        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }
        
        // Validation
        $rules = [
            'name' => 'string|max:255',
            'price' => 'numeric|min:0',
            'description' => 'string'
        ];
        
        $validation = $this->validate($request, $rules);
        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }
        
        // Update product
        $product = $this->updateProduct($id, $request->get_params());
        
        return $this->success($product, 'Product updated successfully');
    }

    /**
     * Remove the specified product
     */
    public function destroy(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        
        // Check if product exists
        $product = $this->getProduct($id);
        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }
        
        // Delete product
        $this->deleteProduct($id);
        
        return $this->success([], 'Product deleted successfully');
    }

    // Helper methods
    private function getProducts($request) { /* ... */ }
    private function getProduct($id) { /* ... */ }
    private function createProduct($data) { /* ... */ }
    private function updateProduct($id, $data) { /* ... */ }
    private function deleteProduct($id) { /* ... */ }
}
```

## BaseController Features

The BaseController provides helpful methods:

### Response Methods

```php
// Success responses
return $this->success($data);
return $this->success($data, 'Custom message', 201);

// Error responses
return $this->error($errors, 'Error message', 400);
return $this->error([], 'Not found', 404);

// Custom responses
return $this->response($data, 200, ['Custom-Header' => 'Value']);
```

### Validation

```php
$rules = [
    'name' => 'required|string|max:255',
    'email' => 'required|email',
    'age' => 'integer|min:18|max:100',
    'website' => 'url'
];

$validation = $this->validate($request, $rules);
if ($validation !== true) {
    return $this->error($validation, 'Validation failed', 422);
}
```

### Authentication Helpers

```php
// Check if user is authenticated
if (!$this->isAuthenticated()) {
    return $this->unauthorized();
}

// Get current user
$user = $this->getCurrentUser();

// Check capabilities
if (!$this->can('manage_options')) {
    return $this->forbidden();
}
```

### Pagination

```php
$page = $request->get_param('page') ?: 1;
$perPage = $request->get_param('per_page') ?: 10;

$products = $this->getProducts();
$paginated = $this->paginate($products, $page, $perPage);

return $this->success($paginated);
```

## Controller Loading

Controllers are automatically discovered and loaded:

```php
// These are equivalent:
$controller = new ProductController();

// Or use helper (with auto-loading)
$controllerName = controller('ProductController');
$controller = new $controllerName();

// Or use WordPress Routes auto-loading
$controller = load_controller('ProductController');
```

## Nested Controllers

Organize controllers in subdirectories:

```bash
# Create nested controller
wp wproutes make:controller Admin/UserController --api
wp wproutes make:controller Api/V2/ProductController --resource
```

Directory structure:
```
controllers/
├── Admin/
│   ├── UserController.php
│   └── SettingsController.php
├── Api/
│   └── V2/
│       └── ProductController.php
└── ProductController.php
```

## Controller Discovery

List all discovered controllers:

```bash
wp wproutes controller:list
wp wproutes controller:list --format=json
```

## Integration with WordPress

### Using WordPress Functions

```php
class PostController extends BaseController
{
    public function index(WP_REST_Request $request)
    {
        // Use WordPress functions
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 10
        ]);
        
        return $this->success($posts);
    }
    
    public function store(WP_REST_Request $request)
    {
        // Create WordPress post
        $post_id = wp_insert_post([
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_status' => 'publish'
        ]);
        
        if (is_wp_error($post_id)) {
            return $this->error([], $post_id->get_error_message(), 400);
        }
        
        return $this->success(['id' => $post_id], 'Post created', 201);
    }
}
```

### Using WordPress ORM

If you're using WordPress ORM alongside WordPress Routes:

```php
class ProductController extends BaseController
{
    public function index(WP_REST_Request $request)
    {
        // Load the Product model
        $Product = model('Product');
        
        // Query using ORM
        $products = $Product::where('active', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return $this->success($products);
    }
}
```

## Best Practices

1. **Extend BaseController**: Use BaseController for API controllers
2. **Use Resource Controllers**: For CRUD operations, use resource controllers
3. **Validate Input**: Always validate incoming request data
4. **Handle Errors**: Provide meaningful error messages
5. **Follow REST Conventions**: Use appropriate HTTP status codes
6. **Organize Logically**: Group related controllers in subdirectories
7. **Document APIs**: Add PHPDoc comments to controller methods

---

Next: [Middleware →](middleware.md)