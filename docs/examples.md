# Examples

This document provides comprehensive examples of using WordPress Routes in real-world scenarios.

## Basic API Example

### 1. Setup

```php
// functions.php (Theme) or main plugin file
define("WPROUTES_MODE", "theme"); // or "plugin"
require_once get_template_directory() . "/vendor/wordpress-routes/bootstrap.php";

// Routes are auto-scaffolded in /routes directory
// No manual setup needed - routes/api.php, routes/web.php, routes/auth.php created automatically
```

### 2. Create Controller

```bash
wp routes:make-controller PostController --api --resource
```

### 3. Define Routes

```php
// routes/api.php (auto-generated)
use WordPressRoutes\Routing\Route;

// API Routes with groups
Route::group(['prefix' => 'v1'], function() {
    Route::get('posts', 'PostController@index');
    Route::post('posts', 'PostController@store');
    Route::get('posts/{id}', 'PostController@show');
    Route::put('posts/{id}', 'PostController@update');
    Route::delete('posts/{id}', 'PostController@destroy');
});
```

### 4. Access API

```bash
# List posts
curl -X GET "https://yoursite.com/wp-json/wp/v2/v1/posts"

# Get specific post
curl -X GET "https://yoursite.com/wp-json/wp/v2/v1/posts/123"

# Create post (authenticated)
curl -X POST "https://yoursite.com/wp-json/myapp/v1/posts" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "New Post", "content": "Post content"}'
```

## E-commerce API Example

### 1. Create Models (using WordPress ORM)

```bash
wp orm:make-model Product --migration
wp orm:make-model Category --migration
wp orm:make-model Order --migration
```

### 2. Create Controllers

```bash
wp routes:make-controller ProductController --api --resource
wp routes:make-controller CategoryController --api --resource
wp routes:make-controller OrderController --api --resource
wp routes:make-controller CartController --api
```

### 3. Create Middleware

```bash
wp routes:make-middleware AuthMiddleware
wp routes:make-middleware AdminMiddleware
wp routes:make-middleware RateLimitMiddleware
```

### 4. Define Routes

```php
// functions.php
add_action('rest_api_init', function() {
    \WordPressRoutes\Routing\Route::setNamespace('shop/v1');

    // Public routes
    \WordPressRoutes\Routing\Route::get('products', 'ProductController@index');
    \WordPressRoutes\Routing\Route::get('products/{id}', 'ProductController@show');
    \WordPressRoutes\Routing\Route::get('categories', 'CategoryController@index');

    // Authenticated routes
    \WordPressRoutes\Routing\Route::group(['middleware' => ['auth']], function() {
        // Cart operations
        \WordPressRoutes\Routing\Route::get('cart', 'CartController@show');
        \WordPressRoutes\Routing\Route::post('cart/items', 'CartController@addItem');
        \WordPressRoutes\Routing\Route::delete('cart/items/{id}', 'CartController@removeItem');

        // Orders
        \WordPressRoutes\Routing\Route::resource('orders', 'OrderController')
            ->only(['index', 'show', 'store']);
    });

    // Admin routes
    \WordPressRoutes\Routing\Route::group(['middleware' => ['auth', 'admin']], function() {
        \WordPressRoutes\Routing\Route::resource('products', 'ProductController')
            ->except(['index', 'show']);
        \WordPressRoutes\Routing\Route::resource('categories', 'CategoryController')
            ->except(['index']);
        \WordPressRoutes\Routing\Route::resource('orders', 'OrderController')
            ->only(['update', 'destroy']);
    });
});
```

### 5. ProductController Implementation

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
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $category = $request->get_param('category');

        // Load Product model
        $Product = model('Product');

        $query = $Product::where('status', 'active');

        if ($category) {
            $query->where('category_id', $category);
        }

        $products = $query->orderBy('created_at', 'desc')
            ->limit($per_page)
            ->offset(($page - 1) * $per_page)
            ->get();

        $total = $Product::where('status', 'active')->count();

        return $this->success([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'pages' => ceil($total / $per_page)
            ]
        ]);
    }

    public function show(WP_REST_Request $request)
    {
        $id = $request->get_param('id');

        $Product = model('Product');
        $product = $Product::with(['category', 'images'])->find($id);

        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }

        return $this->success($product);
    }

    public function store(WP_REST_Request $request)
    {
        $validation = $this->validate($request, [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|integer',
            'description' => 'string',
            'sku' => 'string|max:100'
        ]);

        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }

        $Product = model('Product');
        $product = $Product::create($request->get_params());

        return $this->success($product, 'Product created successfully', 201);
    }

    public function update(WP_REST_Request $request)
    {
        $id = $request->get_param('id');

        $Product = model('Product');
        $product = $Product::find($id);

        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }

        $validation = $this->validate($request, [
            'name' => 'string|max:255',
            'price' => 'numeric|min:0',
            'category_id' => 'integer',
            'description' => 'string',
            'sku' => 'string|max:100'
        ]);

        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }

        $product->update($request->get_params());

        return $this->success($product, 'Product updated successfully');
    }

    public function destroy(WP_REST_Request $request)
    {
        $id = $request->get_param('id');

        $Product = model('Product');
        $product = $Product::find($id);

        if (!$product) {
            return $this->error([], 'Product not found', 404);
        }

        $product->delete();

        return $this->success([], 'Product deleted successfully');
    }
}
```

## Blog API Example

### 1. Setup with Custom Post Types

```php
// functions.php - Register custom post type
function register_blog_post_type() {
    register_post_type('blog_post', [
        'public' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt']
    ]);
}
add_action('init', 'register_blog_post_type');
```

### 2. Create Controllers

```bash
wp routes:make-controller BlogController --api --resource
wp routes:make-controller CommentController --api --resource
wp routes:make-controller AuthorController --api
```

### 3. BlogController with WordPress Integration

```php
<?php
// controllers/BlogController.php

use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;

class BlogController extends BaseController
{
    public function index(WP_REST_Request $request)
    {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;
        $search = $request->get_param('search');
        $author = $request->get_param('author');

        $args = [
            'post_type' => 'blog_post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page
        ];

        if ($search) {
            $args['s'] = sanitize_text_field($search);
        }

        if ($author) {
            $args['author'] = intval($author);
        }

        $query = new WP_Query($args);

        $posts = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => $post->post_excerpt,
                'content' => $post->post_content,
                'author' => [
                    'id' => $post->post_author,
                    'name' => get_the_author_meta('display_name', $post->post_author)
                ],
                'featured_image' => get_the_post_thumbnail_url($post->ID, 'large'),
                'published_at' => $post->post_date,
                'permalink' => get_permalink($post->ID)
            ];
        }, $query->posts);

        return $this->success([
            'posts' => $posts,
            'pagination' => [
                'page' => $page,
                'per_page' => $per_page,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages
            ]
        ]);
    }

    public function show(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'blog_post' || $post->post_status !== 'publish') {
            return $this->error([], 'Post not found', 404);
        }

        // Get comments
        $comments = get_comments([
            'post_id' => $id,
            'status' => 'approve'
        ]);

        return $this->success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
            'excerpt' => $post->post_excerpt,
            'author' => [
                'id' => $post->post_author,
                'name' => get_the_author_meta('display_name', $post->post_author),
                'avatar' => get_avatar_url($post->post_author)
            ],
            'featured_image' => get_the_post_thumbnail_url($id, 'large'),
            'published_at' => $post->post_date,
            'comments_count' => count($comments),
            'tags' => get_the_tags($id),
            'categories' => get_the_category($id)
        ]);
    }

    public function store(WP_REST_Request $request)
    {
        if (!current_user_can('publish_posts')) {
            return $this->forbidden('You do not have permission to create posts');
        }

        $validation = $this->validate($request, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'string',
            'status' => 'in:draft,publish'
        ]);

        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }

        $post_id = wp_insert_post([
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_excerpt' => $request->get_param('excerpt'),
            'post_status' => $request->get_param('status') ?: 'draft',
            'post_type' => 'blog_post',
            'post_author' => get_current_user_id()
        ]);

        if (is_wp_error($post_id)) {
            return $this->error([], $post_id->get_error_message(), 500);
        }

        $post = get_post($post_id);

        return $this->success([
            'id' => $post->ID,
            'title' => $post->post_title,
            'status' => $post->post_status,
            'permalink' => get_permalink($post->ID)
        ], 'Post created successfully', 201);
    }
}
```

## Authentication Example

### 1. JWT Authentication Middleware

```php
<?php
// middleware/JWTAuthMiddleware.php

use WordPressRoutes\Routing\Middleware\MiddlewareInterface;
use WP_REST_Request;
use WP_REST_Response;

class JWTAuthMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            return $this->unauthorizedResponse('Token missing');
        }

        $user_id = $this->validateToken($token);

        if (!$user_id) {
            return $this->unauthorizedResponse('Invalid token');
        }

        wp_set_current_user($user_id);

        return $next($request);
    }

    private function getTokenFromRequest(WP_REST_Request $request)
    {
        $auth_header = $request->get_header('authorization');

        if (!$auth_header) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function validateToken($token)
    {
        // Implement JWT validation logic
        // This is a simplified example
        try {
            $payload = jwt_decode($token, JWT_SECRET, ['HS256']);
            return $payload->user_id ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function unauthorizedResponse($message)
    {
        return new WP_REST_Response([
            'success' => false,
            'message' => $message
        ], 401);
    }
}
```

### 2. Auth Controller

```bash
wp routes:make-controller AuthController --api
```

```php
<?php
// controllers/AuthController.php

use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;

class AuthController extends BaseController
{
    public function login(WP_REST_Request $request)
    {
        $validation = $this->validate($request, [
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }

        $credentials = [
            'user_login' => $request->get_param('username'),
            'user_password' => $request->get_param('password')
        ];

        $user = wp_authenticate($credentials['user_login'], $credentials['user_password']);

        if (is_wp_error($user)) {
            return $this->error([], 'Invalid credentials', 401);
        }

        $token = $this->generateJWTToken($user);

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name
            ]
        ], 'Login successful');
    }

    public function register(WP_REST_Request $request)
    {
        $validation = $this->validate($request, [
            'username' => 'required|string|min:3',
            'email' => 'required|email',
            'password' => 'required|string|min:6'
        ]);

        if ($validation !== true) {
            return $this->error($validation, 'Validation failed', 422);
        }

        if (username_exists($request->get_param('username'))) {
            return $this->error(['username' => ['Username already exists']], 'Username taken', 422);
        }

        if (email_exists($request->get_param('email'))) {
            return $this->error(['email' => ['Email already registered']], 'Email taken', 422);
        }

        $user_id = wp_create_user(
            $request->get_param('username'),
            $request->get_param('password'),
            $request->get_param('email')
        );

        if (is_wp_error($user_id)) {
            return $this->error([], $user_id->get_error_message(), 500);
        }

        $user = get_user_by('id', $user_id);
        $token = $this->generateJWTToken($user);

        return $this->success([
            'token' => $token,
            'user' => [
                'id' => $user->ID,
                'username' => $user->user_login,
                'email' => $user->user_email
            ]
        ], 'Registration successful', 201);
    }

    public function me(WP_REST_Request $request)
    {
        $user = wp_get_current_user();

        return $this->success([
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles
        ]);
    }

    private function generateJWTToken($user)
    {
        $payload = [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ];

        return jwt_encode($payload, JWT_SECRET);
    }
}
```

### 3. Routes with Authentication

```php
// functions.php
add_action('rest_api_init', function() {
    \WordPressRoutes\Routing\Route::setNamespace('api/v1');

    // Public auth routes
    \WordPressRoutes\Routing\Route::post('auth/login', 'AuthController@login');
    \WordPressRoutes\Routing\Route::post('auth/register', 'AuthController@register');

    // Protected routes
    \WordPressRoutes\Routing\Route::group(['middleware' => ['jwt_auth']], function() {
        \WordPressRoutes\Routing\Route::get('auth/me', 'AuthController@me');
        \WordPressRoutes\Routing\Route::resource('posts', 'BlogController');
    });
});
```

## Testing Examples

### Unit Testing Controllers

```php
<?php
// tests/ProductControllerTest.php

class ProductControllerTest extends WP_UnitTestCase
{
    private $controller;

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProductController();
    }

    public function test_index_returns_products()
    {
        // Arrange
        $request = new WP_REST_Request('GET', '/products');

        // Act
        $response = $this->controller->index($request);

        // Assert
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('products', $data);
    }

    public function test_store_creates_product()
    {
        // Arrange
        wp_set_current_user(1); // Admin user
        $request = new WP_REST_Request('POST', '/products');
        $request->set_body_params([
            'name' => 'Test Product',
            'price' => 99.99,
            'category_id' => 1
        ]);

        // Act
        $response = $this->controller->store($request);

        // Assert
        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertEquals('Test Product', $data['name']);
    }
}
```

## Complete Project Structure

```
wp-content/themes/my-theme/
├── lib/
│   ├── wp-orm/              # WordPress ORM library
│   └── wp-routes/           # WordPress Routes library
├── models/                  # Your models
│   ├── Product.php
│   ├── Category.php
│   └── Order.php
├── controllers/             # Your controllers
│   ├── ProductController.php
│   ├── CategoryController.php
│   ├── OrderController.php
│   ├── AuthController.php
│   └── Admin/
│       └── AdminController.php
├── middleware/              # Your middleware
│   ├── AuthMiddleware.php
│   ├── AdminMiddleware.php
│   └── RateLimitMiddleware.php
├── database/
│   └── migrations/          # Your migrations
└── functions.php            # Configuration and routes
```

These examples demonstrate the power and flexibility of WordPress Routes for building robust APIs in WordPress. The system scales from simple endpoints to complex applications with authentication, validation, and proper separation of concerns.

---

Next: [WordPress Integration →](wordpress-integration.md)
