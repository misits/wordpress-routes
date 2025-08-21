# Enhanced Validation System

WordPress Routes now features a comprehensive Laravel-inspired validation system with over 50 built-in validation rules, custom messages, conditional validation, and form request classes.

## Basic Validation

### Simple Usage

```php
use WordPressRoutes\Routing\RouteRequest;

public function store(RouteRequest $request)
{
    $validation = $request->validate([
        'name' => 'required|string|min:2|max:100',
        'email' => 'required|email|unique:users,email',
        'age' => 'required|integer|min:18|max:120',
        'password' => 'required|string|min:8|confirmed',
        'website' => 'nullable|url',
        'tags' => 'array|min:1',
        'tags.*' => 'string|max:50'
    ]);

    if (is_wp_error($validation)) {
        return $validation; // Returns 422 with detailed errors
    }

    // Use validated data
    $validatedData = $validation;
    return $this->success($validatedData);
}
```

### Custom Messages and Attributes

```php
$validation = $request->validate([
    'email' => 'required|email',
    'name' => 'required|min:2'
], [
    // Custom error messages
    'email.required' => 'We need your email address!',
    'email.email' => 'Please provide a valid email address.',
    'name.min' => 'Your name must be at least 2 characters long.'
], [
    // Custom attribute names
    'email' => 'email address',
    'name' => 'full name'
]);
```

## Complete List of Validation Rules

### Required Rules
```php
'required'              // Must be present and not empty
'required_if:field,value' // Required when another field equals value
'required_unless:field,value' // Required unless another field equals value
'required_with:field1,field2' // Required when any of these fields are present
'required_with_all:field1,field2' // Required when all these fields are present
'required_without:field1,field2' // Required when any of these fields are missing
'required_without_all:field1,field2' // Required when all these fields are missing
'filled'                // Must have value when present
'present'               // Must be present (can be empty)
'nullable'              // Can be null
```

### String Validation
```php
'string'                // Must be a string
'alpha'                 // Only letters (Unicode support)
'alpha_num'             // Only letters and numbers
'alpha_dash'            // Letters, numbers, dashes, underscores
'ascii'                 // 7-bit ASCII characters only
'min:3'                 // Minimum length
'max:255'               // Maximum length
'size:10'               // Exact length
'between:5,10'          // Length between min and max
'starts_with:Mr,Mrs,Ms' // Must start with one of these values
'ends_with:.com,.org'   // Must end with one of these values
'regex:/^[A-Z0-9]+$/'   // Must match regular expression
'lowercase'             // Must be lowercase
'uppercase'             // Must be uppercase
```

### Numeric Validation
```php
'numeric'               // Must be a number
'integer'               // Must be an integer
'decimal:2'             // Must have exactly 2 decimal places
'min:18'                // Minimum numeric value
'max:100'               // Maximum numeric value
'between:18,65'         // Numeric value between min and max
'gt:field'              // Greater than another field
'gte:field'             // Greater than or equal to another field
'lt:field'              // Less than another field
'lte:field'             // Less than or equal to another field
'multiple_of:5'         // Must be a multiple of value
'digits:4'              // Must be exactly 4 digits
'digits_between:3,6'    // Must be between 3 and 6 digits
```

### Boolean and Acceptance
```php
'boolean'               // Must be true, false, 0, 1, "0", "1"
'accepted'              // Must be yes, on, 1, or true
'accepted_if:field,value' // Accepted when another field equals value
'declined'              // Must be no, off, 0, or false
'declined_if:field,value' // Declined when another field equals value
```

### Array Validation
```php
'array'                 // Must be an array
'array:key1,key2'       // Array with only these keys
'min:2'                 // Minimum array length
'max:10'                // Maximum array length
'size:5'                // Exact array length
'distinct'              // Array values must be unique
'list'                  // Array with consecutive numeric keys (0,1,2...)
```

### Date Validation
```php
'date'                  // Must be a valid date
'date_format:Y-m-d'     // Must match specific format
'date_equals:2025-01-01' // Must equal specific date
'before:tomorrow'       // Must be before date
'before_or_equal:today' // Must be before or equal to date
'after:yesterday'       // Must be after date
'after_or_equal:2025-01-01' // Must be after or equal to date
```

### File Validation
```php
'file'                  // Must be a successfully uploaded file
'image'                 // Must be an image (jpeg, png, bmp, gif, svg, webp)
'mimes:jpeg,png,pdf'    // Must be one of these MIME types
'max:2048'              // Maximum file size in kilobytes
'dimensions:min_width=100,min_height=100' // Image dimensions
'dimensions:max_width=1000,max_height=1000' // Max image dimensions
'dimensions:ratio=3/2'  // Image aspect ratio
```

### Comparison Rules
```php
'same:password'         // Must match another field
'different:username'    // Must be different from another field
'confirmed'             // Must have matching {field}_confirmation field
'in:admin,editor,author' // Must be one of these values
'not_in:guest,banned'   // Must not be one of these values
```

### Format Validation
```php
'email'                 // Valid email address
'url'                   // Valid URL
'ip'                    // Valid IP address (v4 or v6)
'ipv4'                  // Valid IPv4 address
'ipv6'                  // Valid IPv6 address
'json'                  // Valid JSON string
'uuid'                  // Valid UUID
'timezone'              // Valid timezone identifier
```

### Database Validation
```php
'exists:users,email'    // Value must exist in database table
'exists:users,email,id,123' // Exists but ignore specific ID
'unique:users,email'    // Value must be unique in table
'unique:users,email,123' // Unique but ignore specific ID
```

### WordPress-Specific Rules
```php
'user_exists'           // WordPress user ID must exist
'post_exists'           // WordPress post ID must exist
'term_exists'           // WordPress term ID must exist
'capability'            // User must have this capability
'post_type'             // Must be a valid post type
'taxonomy'              // Must be a valid taxonomy
'user_role'             // Must be a valid user role
'slug'                  // Valid WordPress slug format
'shortcode'             // Shortcode must exist
```

## Advanced Usage

### Conditional Validation

```php
// Validate credit card only if payment method is 'card'
$rules = [
    'payment_method' => 'required|in:cash,card,bank',
    'credit_card' => 'required_if:payment_method,card|digits:16',
    'cvv' => 'required_if:payment_method,card|digits:3',
    'expiry_date' => 'required_if:payment_method,card|date_format:m/y|after:today'
];
```

### Array and Nested Validation

```php
$rules = [
    'products' => 'required|array|min:1',
    'products.*.name' => 'required|string|max:255',
    'products.*.price' => 'required|numeric|min:0',
    'products.*.category' => 'required|exists:categories,id',
    'products.*.tags' => 'array',
    'products.*.tags.*' => 'string|max:50'
];
```

### Custom Validation Messages

```php
$messages = [
    'products.*.name.required' => 'Each product must have a name.',
    'products.*.price.numeric' => 'Product prices must be numbers.',
    'products.*.price.min' => 'Product prices cannot be negative.',
    'email.unique' => 'This email address is already registered.',
    'password.confirmed' => 'Password confirmation does not match.'
];

$validation = $request->validate($rules, $messages);
```

### Custom Attribute Names

```php
$attributes = [
    'first_name' => 'first name',
    'last_name' => 'last name',
    'email' => 'email address',
    'phone_number' => 'phone number'
];

$validation = $request->validate($rules, $messages, $attributes);
```

## Form Request Validation

Create dedicated validation classes for complex forms:

```php
// Create: forms/CreateUserRequest.php
use WordPressRoutes\Validation\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize()
    {
        // Check if user can create other users
        return current_user_can('create_users');
    }

    public function rules()
    {
        return [
            'username' => 'required|string|min:3|max:60|unique:users,user_login',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|string|min:8|confirmed',
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'role' => 'required|user_role',
            'bio' => 'nullable|string|max:500',
            'website' => 'nullable|url'
        ];
    }

    public function messages()
    {
        return [
            'username.unique' => 'This username is already taken.',
            'email.unique' => 'This email is already registered.',
            'role.user_role' => 'Please select a valid user role.'
        ];
    }

    public function attributes()
    {
        return [
            'username' => 'username',
            'email' => 'email address',
            'first_name' => 'first name',
            'last_name' => 'last name'
        ];
    }
}
```

### Using Form Requests in Controllers

```php
use WordPressRoutes\Routing\BaseController;
use WordPressRoutes\Routing\RouteRequest;

class UserController extends BaseController
{
    public function store(RouteRequest $request)
    {
        $formRequest = new CreateUserRequest($request);
        
        try {
            $validatedData = $formRequest->validated();
        } catch (\WP_Error $error) {
            return $error;
        }

        // Create user with validated data
        $user_id = wp_create_user(
            $validatedData['username'],
            $validatedData['password'],
            $validatedData['email']
        );

        return $this->success(['user_id' => $user_id], 'User created successfully', 201);
    }
}
```

## Validation Methods

### Check Validation Status

```php
// Check if validation passes without running it
if ($request->passes($rules)) {
    // Handle valid data
}

// Check if validation fails
if ($request->fails($rules)) {
    // Handle validation errors
}

// Get validator instance for custom logic
$validator = $request->validator($rules, $messages, $attributes);
if ($validator->fails()) {
    $errors = $validator->errors();
    // Handle errors manually
}
```

### Manual Validation

```php
use WordPressRoutes\Validation\Validator;

$validator = Validator::make($data, $rules, $messages, $attributes);

if ($validator->fails()) {
    $errors = $validator->errors();
    return new WP_Error('validation_failed', 'Validation failed', [
        'errors' => $errors,
        'status' => 422
    ]);
}

$validatedData = $validator->validate();
```

## Real-World Examples

### User Registration

```php
public function register(RouteRequest $request)
{
    $validation = $request->validate([
        'username' => 'required|string|min:3|max:60|alpha_dash|unique:users,user_login',
        'email' => 'required|email|max:254|unique:users,user_email',
        'password' => 'required|string|min:8|confirmed',
        'first_name' => 'required|string|max:50|alpha',
        'last_name' => 'required|string|max:50|alpha',
        'age' => 'required|integer|min:13|max:120',
        'terms_accepted' => 'required|accepted',
        'newsletter' => 'boolean',
        'phone' => 'nullable|regex:/^[\+]?[\d\s\-\(\)]+$/',
        'website' => 'nullable|url|max:255'
    ], [
        'username.alpha_dash' => 'Username can only contain letters, numbers, dashes and underscores.',
        'username.unique' => 'This username is already taken.',
        'email.unique' => 'An account with this email already exists.',
        'password.confirmed' => 'Password confirmation does not match.',
        'age.min' => 'You must be at least 13 years old to register.',
        'terms_accepted.accepted' => 'You must accept the terms and conditions.'
    ]);

    if (is_wp_error($validation)) {
        return $validation;
    }

    // Create user with validated data
    $user_id = wp_create_user(
        $validation['username'],
        $validation['password'],
        $validation['email']
    );

    return $this->success(['user_id' => $user_id], 'Registration successful', 201);
}
```

### Product Management

```php
public function createProduct(RouteRequest $request)
{
    $validation = $request->validate([
        'name' => 'required|string|min:2|max:255',
        'description' => 'required|string|min:10|max:2000',
        'price' => 'required|numeric|min:0|max:999999.99',
        'category_id' => 'required|exists:product_categories,id',
        'sku' => 'required|string|max:50|unique:products,sku',
        'stock_quantity' => 'required|integer|min:0',
        'weight' => 'nullable|numeric|min:0',
        'dimensions' => 'nullable|array',
        'dimensions.length' => 'required_with:dimensions|numeric|min:0',
        'dimensions.width' => 'required_with:dimensions|numeric|min:0',
        'dimensions.height' => 'required_with:dimensions|numeric|min:0',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
        'images' => 'array|max:10',
        'images.*' => 'file|image|max:2048|dimensions:min_width=100,min_height=100',
        'status' => 'required|in:draft,published,archived',
        'featured' => 'boolean',
        'sale_price' => 'nullable|numeric|min:0|lt:price',
        'sale_start' => 'nullable|date|required_with:sale_price',
        'sale_end' => 'nullable|date|after:sale_start'
    ], [
        'sale_price.lt' => 'Sale price must be less than regular price.',
        'sale_end.after' => 'Sale end date must be after sale start date.'
    ]);

    if (is_wp_error($validation)) {
        return $validation;
    }

    // Create product with validated data
    return $this->success($validation, 'Product created successfully', 201);
}
```

### File Upload with Validation

```php
public function uploadDocument(RouteRequest $request)
{
    $validation = $request->validate([
        'document' => 'required|file|mimes:pdf,doc,docx|max:10240', // 10MB max
        'title' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'category' => 'required|exists:document_categories,id',
        'access_level' => 'required|in:public,private,restricted',
        'tags' => 'array',
        'tags.*' => 'string|max:50',
        'notify_users' => 'array',
        'notify_users.*' => 'user_exists'
    ], [
        'document.mimes' => 'Document must be a PDF or Word document.',
        'document.max' => 'Document size cannot exceed 10MB.',
        'notify_users.*.user_exists' => 'One of the selected users does not exist.'
    ]);

    if (is_wp_error($validation)) {
        return $validation;
    }

    // Handle file upload with validated data
    return $this->success($validation, 'Document uploaded successfully', 201);
}
```

## Error Response Format

Validation errors return a standardized WP_Error with detailed information:

```json
{
    "code": "validation_failed",
    "message": "Validation failed",
    "data": {
        "status": 422,
        "errors": {
            "email": [
                "The email field is required.",
                "The email field must be a valid email address."
            ],
            "password": [
                "The password field must be at least 8 characters."
            ],
            "products.0.name": [
                "Each product must have a name."
            ]
        }
    }
}
```

## Performance Tips

1. **Use `nullable`** instead of `required` when fields are optional
2. **Validate arrays efficiently** using dot notation
3. **Database rules** (`exists`, `unique`) make database queries - use sparingly
4. **File validation** should be combined with server-side file type checking
5. **Cache validation results** for repeated validations of the same data

## Security Considerations

1. **Always validate on the server** - never trust client-side validation alone
2. **Use `exists` and `unique` rules** to prevent database inconsistencies
3. **Validate file uploads carefully** - combine with WordPress file handling functions
4. **Sanitize after validation** - validation doesn't sanitize data
5. **Use capability checks** in form requests for authorization

## Migration from Old System

The new validation system is backward compatible. Old validation:

```php
// Old way (still works)
$validation = $request->validate([
    'email' => 'required|email',
    'name' => 'required|min:2'
]);
```

New enhanced features:

```php
// New way (recommended)
$validation = $request->validate([
    'email' => 'required|email|unique:users,user_email',
    'name' => 'required|string|min:2|max:100|alpha',
    'age' => 'required|integer|between:18,120'
], [
    'email.unique' => 'This email is already registered.'
], [
    'email' => 'email address'
]);
```

## Next Steps

- [Security Best Practices →](security.md)
- [Testing Your Validation →](testing.md)
- [Form Requests →](form-requests.md)