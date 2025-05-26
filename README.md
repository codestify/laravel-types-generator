# Laravel TypeScript Types Generator

Generate TypeScript types directly from your Laravel Resources and Controllers. Keep your frontend types in sync with your backend without manual definitions.

## Why This Package?

Building a Laravel API with a TypeScript frontend? You know the pain of keeping types synchronized. This package eliminates that by automatically generating TypeScript types from your actual API responses.

## Installation

Install via Composer:

```bash
composer require codemystify/laravel-types-generator
```

Publish the config file:

```bash
php artisan vendor:publish --tag=types-generator-config
```

## Quick Start

1. **Annotate your Resource methods:**

```php
use Codemystify\TypesGenerator\Attributes\GenerateTypes;

class UserResource extends JsonResource
{
    #[GenerateTypes(name: 'User', group: 'auth')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
```
2. **Generate types:**

```bash
php artisan generate:types
```

3. **Use in your frontend:**

```typescript
import type { User } from '@/types/generated';

const user: User = {
    id: 1,
    name: 'John Doe',
    email: 'john@example.com',
    created_at: '2024-01-01T00:00:00Z',
};
```

## Features

### Automatic Type Generation
- Generates TypeScript interfaces from Resource `toArray()` methods
- Supports nested objects and arrays
- Handles nullable fields automatically
- Groups types by category for better organization

### Smart Type Detection
- Analyzes actual return values, not just database schema
- Supports complex nested structures
- Handles Laravel's conditional fields (`$this->when()`)
- Detects and creates separate types for nested objects

### Performance & Caching
- Built-in caching to speed up regeneration
- Validates file changes before regenerating
- Configurable cache TTL
- Memory-efficient processing for large codebases

### Flexible Configuration
- Customize output paths and filenames
- Control what gets included/excluded
- Set type generation options
- Configure validation rules

### Command Options

```bash
# Generate all types
php artisan generate:types

# Generate specific group only
php artisan generate:types --group=events

# Force regeneration (ignores cache)
php artisan generate:types --force

# Dry run - see what would be generated
php artisan generate:types --dry-run

# Verbose output for debugging
php artisan generate:types --verbose
```
## Advanced Usage

### Complex Nested Types

```php
class ProductResource extends JsonResource
{
    #[GenerateTypes(name: 'Product', group: 'catalog')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ],
            'reviews' => $this->reviews->map(fn($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'author' => $review->user->name,
            ]),
            'variants' => $this->variants->map(fn($variant) => [
                'id' => $variant->id,
                'size' => $variant->size,
                'color' => $variant->color,
                'stock' => $variant->stock,
            ]),
        ];
    }
}
```

This generates clean, typed interfaces:

```typescript
export interface Product {
    id: number;
    name: string;
    price: number;
    category: CategoryType;
    reviews: ReviewType[];
    variants: VariantType[];
}

export interface CategoryType {
    id: number;
    name: string;
    slug: string;
}

export interface ReviewType {
    id: number;
    rating: number;
    comment: string;
    author: string;
}

export interface VariantType {
    id: number;
    size: string;
    color: string;
    stock: number;
}
```
### Controller Methods

You can also generate types from Controller methods that return JSON:

```php
class DashboardController extends Controller
{
    #[GenerateTypes(name: 'DashboardStats', group: 'dashboard')]
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_users' => User::count(),
            'active_users' => User::where('active', true)->count(),
            'revenue' => [
                'total' => Order::sum('total'),
                'this_month' => Order::thisMonth()->sum('total'),
                'growth_rate' => 12.5,
            ],
            'recent_orders' => OrderResource::collection(
                Order::latest()->take(5)->get()
            ),
        ]);
    }
}
```

### Type Groups

Organize your types into logical groups:

```php
// Will generate auth.ts
#[GenerateTypes(name: 'User', group: 'auth')]

// Will generate catalog.ts  
#[GenerateTypes(name: 'Product', group: 'catalog')]

// Will generate dashboard.ts
#[GenerateTypes(name: 'Stats', group: 'dashboard')]
```
## Configuration

The config file (`config/types-generator.php`) lets you customize everything:

```php
return [
    // Where to save generated files
    'output' => [
        'path' => resource_path('js/types/generated'),
        'filename_pattern' => '{group}.ts',
        'index_file' => true, // Creates index.ts with all exports
        'backup_old_files' => true,
    ],

    // Source directories to scan
    'sources' => [
        'resources_path' => app_path('Http/Resources'),
        'controllers_path' => app_path('Http/Controllers'),
        'models_path' => app_path('Models'),
    ],

    // Type generation options
    'generation' => [
        'include_comments' => true,
        'include_readonly' => false,
        'strict_types' => true,
        'extract_nested_types' => true,
    ],

    // What to exclude
    'exclude' => [
        'methods' => ['password', 'remember_token'],
        'classes' => [],
        'patterns' => ['/test/i', '/mock/i'],
    ],

    // Performance settings
    'performance' => [
        'cache_enabled' => true,
        'cache_ttl' => 3600,
    ],
];
```
## Output Examples

### Basic Interface
```typescript
/**
 * User model type
 * @source UserResource::toArray
 * @group auth
 */
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
}
```

### Complex Nested Types
```typescript
export interface Order {
    id: number;
    status: string;
    total: number;
    customer: CustomerType;
    items: OrderItemType[];
    payment: {
        method: string;
        status: string;
        transaction_id?: string;
    };
}
```

### Index File
When `index_file` is enabled, you get a convenient index.ts:

```typescript
// Re-export all generated types
export * from './auth';
export * from './catalog';
export * from './dashboard';
```

## Best Practices

### 1. Use Descriptive Type Names
```php
#[GenerateTypes(name: 'UserProfile', group: 'users')]
#[GenerateTypes(name: 'UserListItem', group: 'users')]
```

### 2. Group Related Types
```php
// All authentication-related types
#[GenerateTypes(group: 'auth')]

// All catalog-related types  
#[GenerateTypes(group: 'catalog')]
```

### 3. Document Your Types
```php
#[GenerateTypes(
    name: 'Product',
    group: 'catalog',
    description: 'Complete product data with variants and reviews'
)]
```
### 4. Handle Nullable Fields Properly
```php
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'description' => $this->description, // Will be string | null
        'end_date' => $this->when($this->end_date, $this->end_date),
    ];
}
```

## Troubleshooting

### Types not generating?
- Make sure you have the `#[GenerateTypes]` attribute on your methods
- Check that the method returns an array
- Verify your source paths in the config

### Cache issues?
```bash
# Clear the cache
php artisan generate:types --force
```

### Complex nested types not working?
- The package analyzes actual return values
- Make sure your Resource methods return consistent structures
- Use `--verbose` to see what's being generated

## Requirements

- PHP 8.2+
- Laravel 11.0+

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:feature

# Generate coverage report
composer test:coverage
```

## Contributing

Contributions are welcome! Please see our [contributing guide](CONTRIBUTING.md) for details.

## License

MIT License. See [LICENSE](LICENSE) for details.
