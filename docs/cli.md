# CLI Commands

WordPress Routes provides powerful WP-CLI commands for generating controllers, middleware, and managing your API components.

## Available Commands

### Controller Commands

#### make:controller
Generate a new controller class.

```bash
# Basic controller
wp wproutes make:controller ProductController

# API controller (extends BaseController)
wp wproutes make:controller ProductController --api

# Resource controller with CRUD methods
wp wproutes make:controller ProductController --resource

# API + Resource controller (recommended for APIs)
wp wproutes make:controller ProductController --api --resource

# Nested controller
wp wproutes make:controller Admin/UserController --api

# Custom namespace
wp wproutes make:controller ProductController --namespace="MyApp\\Controllers"

# Custom path
wp wproutes make:controller ProductController --path=/custom/controllers
```

**Options:**
- `--api`: Create an API controller that extends BaseController
- `--resource`: Create a resource controller with CRUD methods (index, store, show, update, destroy)
- `--namespace=<namespace>`: Specify custom namespace
- `--path=<path>`: Specify custom path (overrides mode-based detection)

#### controller:list
List all discovered controllers.

```bash
# List all controllers
wp wproutes controller:list

# Output as JSON
wp wproutes controller:list --format=json

# Output as CSV
wp wproutes controller:list --format=csv
```

### Middleware Commands

#### make:middleware
Generate a new middleware class.

```bash
# Basic middleware
wp wproutes make:middleware AuthMiddleware

# Custom namespace
wp wproutes make:middleware CustomAuth --namespace="MyApp\\Middleware"

# Custom path
wp wproutes make:middleware AuthMiddleware --path=/custom/middleware
```

**Options:**
- `--namespace=<namespace>`: Specify custom namespace
- `--path=<path>`: Specify custom path (overrides mode-based detection)

#### middleware:list
List all discovered middleware.

```bash
# List all middleware
wp wproutes middleware:list

# Output as JSON
wp wproutes middleware:list --format=json
```

### Route Commands

#### route:list
List all registered routes.

```bash
# List all registered routes
wp wproutes route:list
```

### Help Command

#### help
Show WordPress Routes CLI help.

```bash
# Show help
wp wproutes help
```

## Command Examples

### Creating Controllers

#### Basic API Controller
```bash
wp wproutes make:controller ProductController --api
```

Creates:
```php
<?php

use WordPressRoutes\Routing\BaseController;
use WP_REST_Request;
use WP_REST_Response;

class ProductController extends BaseController
{
    public function handle(WP_REST_Request $request)
    {
        return $this->success([
            "message" => "Controller method called successfully",
            "data" => $request->get_params()
        ]);
    }
}
```

#### Resource Controller
```bash
wp wproutes make:controller ProductController --api --resource
```

Creates a full CRUD controller with methods:
- `index()` - List all resources
- `store()` - Create new resource
- `show()` - Display specific resource
- `update()` - Update specific resource
- `destroy()` - Delete specific resource

#### Nested Controller
```bash
wp wproutes make:controller Admin/UserController --api --resource
```

Creates: `controllers/Admin/UserController.php`

### Creating Middleware

#### Authentication Middleware
```bash
wp wproutes make:middleware AuthMiddleware
```

Creates:
```php
<?php

use WordPressRoutes\Routing\Middleware\MiddlewareInterface;
use WP_REST_Request;
use WP_REST_Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(WP_REST_Request $request, callable $next)
    {
        // Pre-processing logic
        if (!$this->checkCondition($request)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Middleware condition failed'
            ], 403);
        }
        
        // Continue to next middleware or controller
        $response = $next($request);
        
        // Post-processing logic
        
        return $response;
    }
    
    protected function checkCondition(WP_REST_Request $request): bool
    {
        // Implement your middleware logic
        return true;
    }
}
```

## Mode-based File Creation

Commands automatically detect your mode and create files in the appropriate directories:

### Theme Mode
```bash
# With WPROUTES_MODE = "theme"
wp wproutes make:controller ProductController --api
```

Creates: `/wp-content/themes/your-theme/controllers/ProductController.php`

### Plugin Mode
```bash
# With WPROUTES_MODE = "plugin"  
wp wproutes make:controller ProductController --api
```

Creates: `/wp-content/plugins/your-plugin/src/Controllers/ProductController.php`

## Batch Operations

### Create Multiple Controllers
```bash
# Create multiple controllers at once
wp wproutes make:controller ProductController --api --resource
wp wproutes make:controller CategoryController --api --resource
wp wproutes make:controller UserController --api --resource
wp wproutes make:controller Admin/SettingsController --api
```

### Create Controller with Middleware
```bash
# Create controller and related middleware
wp wproutes make:controller ProductController --api --resource
wp wproutes make:middleware ProductMiddleware
```

## Integration with Existing Workflow

### WordPress Development Workflow
```bash
# 1. Create models (if using WordPress ORM)
wp wporm make:model Product --migration

# 2. Create controller
wp wproutes make:controller ProductController --api --resource

# 3. Create custom middleware if needed
wp wproutes make:middleware ProductAuthMiddleware

# 4. Run migrations
wp wporm migrate

# 5. List everything to verify
wp wporm model:list
wp wproutes controller:list
wp wproutes middleware:list
```

### CI/CD Integration
```bash
#!/bin/bash
# deploy.sh - Example deployment script

# Generate missing controllers
wp wproutes make:controller ApiController --api --resource --allow-root

# Verify all components
wp wproutes controller:list --allow-root
wp wproutes middleware:list --allow-root
wp wproutes route:list --allow-root
```

## Command Output

### Successful Creation
```bash
$ wp wproutes make:controller ProductController --api --resource

Success: Controller ProductController created successfully.
Controller created in: /wp-content/themes/wporm-demo/controllers
```

### Listing Controllers
```bash
$ wp wproutes controller:list

+-------------------+----------------------------------------------------------+--------------------------------------------+
| controller        | file                                                     | path                                       |
+-------------------+----------------------------------------------------------+--------------------------------------------+
| ProductController | /wp-content/themes/wporm-demo/controllers/ProductCon... | /wp-content/themes/wporm-demo/controllers |
| UserController    | /wp-content/themes/wporm-demo/controllers/UserContr...  | /wp-content/themes/wporm-demo/controllers |
+-------------------+----------------------------------------------------------+--------------------------------------------+

Summary: 2 controllers found across 1 directories
```

### Listing Routes
```bash
$ wp wproutes route:list

Registered Routes:

GET    /products                     ProductController@index
POST   /products                     ProductController@store
GET    /products/(?P<id>\d+)         ProductController@show
PUT    /products/(?P<id>\d+)         ProductController@update
DELETE /products/(?P<id>\d+)         ProductController@destroy
```

## Error Handling

### Common Errors

#### Controller Already Exists
```bash
$ wp wproutes make:controller ProductController --api

Error: Controller creation failed: Controller ProductController already exists!
```

#### Invalid Path
```bash
$ wp wproutes make:controller ProductController --path=/invalid/path

Error: Controller creation failed: Directory /invalid/path is not writable
```

#### Missing Mode Configuration
```bash
$ wp wproutes make:controller ProductController

Warning: WPROUTES_MODE not defined, using theme mode by default
Success: Controller ProductController created successfully.
```

## Advanced Usage

### Custom Templates
You can extend the CLI to use custom templates:

```bash
# This would use a custom template if implemented
wp wproutes make:controller ApiController --template=custom-api
```

### Namespace Organization
```bash
# Organize by feature
wp wproutes make:controller Auth/LoginController --namespace="MyApp\\Auth"
wp wproutes make:controller Auth/RegisterController --namespace="MyApp\\Auth"

# Organize by version
wp wproutes make:controller V1/ProductController --namespace="MyApp\\V1"
wp wproutes make:controller V2/ProductController --namespace="MyApp\\V2"
```

### Integration with Other Tools
```bash
# Use with other WordPress tools
wp wproutes make:controller ProductController --api --resource
wp scaffold plugin-tests my-plugin
wp package install wp-cli/doctor-command
wp doctor check
```

## Best Practices

1. **Use Consistent Naming**: Follow naming conventions (Controller suffix, PascalCase)
2. **Leverage Resource Controllers**: Use `--resource` for CRUD operations
3. **Organize with Namespaces**: Use subdirectories and namespaces for large projects
4. **Check Before Creating**: Use list commands to see existing components
5. **Document Generated Code**: Add PHPDoc comments to generated controllers
6. **Use Version Control**: Commit generated files to track changes
7. **Test Generated Code**: Write tests for generated controllers

---

Next: [Examples →](examples.md)