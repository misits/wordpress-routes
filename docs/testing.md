# Testing Guide

WordPress Routes provides comprehensive testing capabilities for your API endpoints. This guide covers testing strategies, built-in tools, and best practices.

## Built-in Testing Interface

WordPress Routes includes a browser-based testing interface for quick endpoint validation:

1. Navigate to your theme's homepage
2. Click the "Routes" tab
3. Enter your API endpoint URL
4. Choose test method:
   - **Test GET**: Simple GET request without authentication
   - **Test GET (Auth)**: GET request with nonce authentication
   - **Test POST**: POST request with JSON data

### Using the Testing Interface

```php
// Your endpoint
Route::get('test-endpoint', function(RouteRequest $request) {
    return ['message' => 'Hello from API', 'time' => current_time('mysql')];
});

// Test in browser:
// URL: /wp-json/myapp/v1/test-endpoint
// Click "Test GET" to see the response
```

## Command Line Testing

### Using cURL

#### Basic GET Request
```bash
curl -X GET "https://yoursite.com/wp-json/myapp/v1/users" \
  -H "Content-Type: application/json"
```

#### POST Request with Data
```bash
curl -X POST "https://yoursite.com/wp-json/myapp/v1/users" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com"
  }'
```

#### Authenticated Request with Nonce
```bash
# First get a nonce (you'll need to extract this from your admin area)
NONCE="your_nonce_here"

curl -X GET "https://yoursite.com/wp-json/myapp/v1/profile" \
  -H "X-WP-Nonce: $NONCE" \
  -H "Content-Type: application/json" \
  --cookie-jar cookies.txt \
  --cookie cookies.txt
```

#### Application Password Authentication
```bash
curl -u "username:application_password" \
  -X GET "https://yoursite.com/wp-json/myapp/v1/profile" \
  -H "Content-Type: application/json"
```

### Using WP-CLI

Test endpoints directly within WordPress context:

```bash
# List all registered routes
wp wproutes route:list

# Test a specific endpoint
wp eval "
Route::get('test', function(\$r) { return ['test' => true]; });
\$response = rest_do_request('/wp-json/myapp/v1/test');
print_r(\$response->get_data());
"
```

## Testing with Postman

### Setup Postman Collection

1. **Create New Collection**: "WordPress Routes API"
2. **Set Base URL**: `{{base_url}}/wp-json/{{namespace}}`
3. **Configure Environment**:
   - `base_url`: `https://yoursite.com`
   - `namespace`: `myapp/v1`

### Authentication Setup

#### Method 1: Nonce Authentication
```javascript
// Pre-request Script
pm.sendRequest({
    url: pm.environment.get("base_url") + "/wp-admin/admin-ajax.php",
    method: 'POST',
    header: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: {
        mode: 'urlencoded',
        urlencoded: [
            {key: 'action', value: 'get_nonce'},
        ]
    }
}, function (err, response) {
    if (response.json().success) {
        pm.environment.set("nonce", response.json().data);
    }
});

// Headers Tab
X-WP-Nonce: {{nonce}}
```

#### Method 2: Basic Authentication
```
Authorization Tab:
Type: Basic Auth
Username: your_username
Password: application_password
```

### Sample Requests

#### GET Users List
```
Method: GET
URL: {{base_url}}/wp-json/{{namespace}}/users
Headers:
  Content-Type: application/json
```

#### POST Create User
```
Method: POST
URL: {{base_url}}/wp-json/{{namespace}}/users
Headers:
  Content-Type: application/json
  X-WP-Nonce: {{nonce}}
Body (raw JSON):
{
    "username": "testuser",
    "email": "test@example.com",
    "password": "secure-password"
}
```

## Unit Testing

### PHPUnit Setup

Create test files for your controllers:

```php
<?php
// tests/UserControllerTest.php

use PHPUnit\Framework\TestCase;
use WordPressRoutes\Routing\RouteRequest;

class UserControllerTest extends TestCase
{
    private $controller;
    
    protected function setUp(): void
    {
        $this->controller = new UserController();
    }
    
    public function testIndexReturnsUsers()
    {
        // Mock RouteRequest
        $request = $this->createMock(RouteRequest::class);
        
        // Call controller method
        $response = $this->controller->index($request);
        
        // Assert response structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
    }
    
    public function testStoreValidatesInput()
    {
        $request = $this->createMock(RouteRequest::class);
        
        // Mock validation failure
        $request->method('validate')
               ->willReturn(new WP_Error('validation_failed', 'Invalid input'));
        
        $response = $this->controller->store($request);
        
        $this->assertInstanceOf(WP_Error::class, $response);
    }
}
```

### Integration Testing

Test full API endpoints with WordPress:

```php
<?php
// tests/ApiIntegrationTest.php

class ApiIntegrationTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        
        // Register your routes
        require_once get_template_directory() . '/routes.php';
        
        // Create test user
        $this->user_id = $this->factory()->user->create([
            'role' => 'administrator'
        ]);
    }
    
    public function testUsersEndpoint()
    {
        // Make authenticated request
        wp_set_current_user($this->user_id);
        
        $request = new WP_REST_Request('GET', '/wp-json/myapp/v1/users');
        $response = rest_do_request($request);
        
        $this->assertEquals(200, $response->get_status());
        $this->assertArrayHasKey('success', $response->get_data());
    }
    
    public function testUnauthorizedAccess()
    {
        $request = new WP_REST_Request('POST', '/wp-json/myapp/v1/admin/users');
        $response = rest_do_request($request);
        
        $this->assertEquals(401, $response->get_status());
    }
}
```

## Testing Middleware

### Testing Authentication Middleware

```php
public function testAuthMiddleware()
{
    $middleware = new AuthMiddleware();
    $request = $this->createMock(RouteRequest::class);
    
    // Test unauthenticated request
    $request->method('isAuthenticated')->willReturn(false);
    $result = $middleware->handle($request);
    
    $this->assertInstanceOf(WP_Error::class, $result);
    $this->assertEquals(401, $result->get_error_data()['status']);
}
```

### Testing Rate Limiting

```php
public function testRateLimitMiddleware()
{
    $middleware = new RateLimitMiddleware(5, 60); // 5 requests per minute
    $request = $this->createMock(RouteRequest::class);
    
    // Simulate multiple requests
    for ($i = 0; $i < 5; $i++) {
        $result = $middleware->handle($request);
        $this->assertNull($result); // Should pass
    }
    
    // 6th request should be rate limited
    $result = $middleware->handle($request);
    $this->assertInstanceOf(WP_Error::class, $result);
    $this->assertEquals(429, $result->get_error_data()['status']);
}
```

## Testing Best Practices

### 1. Test All HTTP Methods

```php
public function testAllHttpMethods()
{
    $endpoints = [
        ['GET', '/users', 200],
        ['POST', '/users', 201],
        ['PUT', '/users/1', 200],
        ['DELETE', '/users/1', 200],
    ];
    
    foreach ($endpoints as [$method, $path, $expectedStatus]) {
        $request = new WP_REST_Request($method, "/wp-json/myapp/v1{$path}");
        $response = rest_do_request($request);
        
        $this->assertEquals($expectedStatus, $response->get_status());
    }
}
```

### 2. Test Error Conditions

```php
public function testErrorHandling()
{
    // Test invalid data
    $request = new WP_REST_Request('POST', '/wp-json/myapp/v1/users');
    $request->set_body('invalid json');
    $response = rest_do_request($request);
    
    $this->assertEquals(400, $response->get_status());
    
    // Test not found
    $request = new WP_REST_Request('GET', '/wp-json/myapp/v1/users/99999');
    $response = rest_do_request($request);
    
    $this->assertEquals(404, $response->get_status());
}
```

### 3. Test Permissions

```php
public function testPermissions()
{
    // Test as different user roles
    $roles = ['subscriber', 'contributor', 'editor', 'administrator'];
    
    foreach ($roles as $role) {
        $user_id = $this->factory()->user->create(['role' => $role]);
        wp_set_current_user($user_id);
        
        $request = new WP_REST_Request('POST', '/wp-json/myapp/v1/admin/action');
        $response = rest_do_request($request);
        
        if ($role === 'administrator') {
            $this->assertEquals(200, $response->get_status());
        } else {
            $this->assertEquals(403, $response->get_status());
        }
    }
}
```

### 4. Test Data Validation

```php
public function testDataValidation()
{
    $testCases = [
        // Valid data
        [['email' => 'test@example.com'], 200],
        // Invalid email
        [['email' => 'invalid-email'], 422],
        // Missing required field
        [[], 422],
    ];
    
    foreach ($testCases as [$data, $expectedStatus]) {
        $request = new WP_REST_Request('POST', '/wp-json/myapp/v1/users');
        $request->set_json_params($data);
        $response = rest_do_request($request);
        
        $this->assertEquals($expectedStatus, $response->get_status());
    }
}
```

## Performance Testing

### Load Testing with Apache Bench

```bash
# Test endpoint performance
ab -n 1000 -c 10 https://yoursite.com/wp-json/myapp/v1/users

# Test with authentication
ab -n 100 -c 5 -H "X-WP-Nonce: your_nonce" \
   https://yoursite.com/wp-json/myapp/v1/profile
```

### Monitoring Response Times

```php
public function testResponseTime()
{
    $start = microtime(true);
    
    $request = new WP_REST_Request('GET', '/wp-json/myapp/v1/users');
    $response = rest_do_request($request);
    
    $duration = microtime(true) - $start;
    
    $this->assertLessThan(1.0, $duration, 'Response took too long');
    $this->assertEquals(200, $response->get_status());
}
```

## Debug Testing

### Enable Debug Mode

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Test with debug info
Route::get('debug', function(RouteRequest $request) {
    return [
        'debug' => WP_DEBUG,
        'user' => $request->user(),
        'params' => $request->all(),
        'memory' => memory_get_usage(true)
    ];
});
```

### Custom Debug Endpoint

```php
if (WP_DEBUG) {
    Route::get('debug/routes', function() {
        return Route::getRegisteredRoutes();
    })->middleware('capability:manage_options');
    
    Route::get('debug/middleware', function() {
        return MiddlewareRegistry::getRegistered();
    })->middleware('capability:manage_options');
}
```

## Continuous Integration

### GitHub Actions Example

```yaml
# .github/workflows/test.yml
name: Test API

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup WordPress
      uses: roots/wordpress-actions@v1
      with:
        wordpress-version: latest
    
    - name: Install dependencies
      run: composer install
    
    - name: Run PHPUnit
      run: ./vendor/bin/phpunit
    
    - name: Test API endpoints
      run: |
        curl -f http://localhost/wp-json/myapp/v1/users
        curl -f -X POST http://localhost/wp-json/myapp/v1/test \
          -H "Content-Type: application/json" \
          -d '{"test": true}'
```

## Testing Checklist

### Before Release
- [ ] All endpoints respond correctly
- [ ] Authentication works for protected routes
- [ ] Validation catches invalid input
- [ ] Error responses are appropriate
- [ ] Rate limiting functions properly
- [ ] Permissions are enforced
- [ ] Performance meets requirements
- [ ] Security vulnerabilities tested

### Automated Tests
- [ ] Unit tests for controllers
- [ ] Integration tests for endpoints
- [ ] Middleware tests
- [ ] Performance benchmarks
- [ ] Security scans

## Troubleshooting Common Issues

### 1. 404 Errors
- Check route registration
- Verify namespace is correct
- Ensure WordPress permalinks are enabled

### 2. Authentication Failures
- Verify nonce is valid and not expired
- Check user permissions
- Confirm cookies are being sent

### 3. CORS Issues
- Add CORS middleware
- Check request headers
- Verify origin is allowed

### 4. Performance Problems
- Add caching middleware
- Optimize database queries
- Implement rate limiting

## Next Steps

- [Security Best Practices →](security.md)
- [Middleware Documentation →](middleware.md)
- [Authentication Guide →](authentication.md)