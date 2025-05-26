# Laravel TypeScript Types Generator

<div align="center">

**🚀 Generate TypeScript types directly from your Laravel Resources and Controllers**

[![Latest Version](https://img.shields.io/packagist/v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![PHP Version](https://img.shields.io/packagist/php-v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012%2B-red.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/codemystify/laravel-types-generator)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-green.svg)](https://github.com/codemystify/laravel-types-generator)

</div>

---

## 📖 Table of Contents

- [Why This Package?](#-why-this-package)
- [Installation](#-installation)
- [Quick Start](#-quick-start)
- [Features](#-features)
- [Command Reference](#-command-reference)
- [Advanced Usage](#-advanced-usage)
- [Configuration](#-configuration)
- [Output Examples](#-output-examples)
- [Best Practices](#-best-practices)
- [Troubleshooting](#-troubleshooting)
- [Requirements](#-requirements)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)

---

## 💡 Why This Package?

Building a Laravel API with a TypeScript frontend? You know the pain of keeping types synchronized between your backend and frontend. Manual type definitions quickly become outdated, leading to runtime errors and frustrated developers.

**This package eliminates that pain** by automatically generating TypeScript types from your actual Laravel API responses.

> **✨ Key Benefits:**
> - 🔄 **Always in sync** - Types are generated from your actual code
> - 🚀 **Zero maintenance** - No manual type definitions to maintain  
> - 🛡️ **Type safety** - Catch errors at compile time, not runtime
> - ⚡ **Developer experience** - Full IntelliSense and autocomplete

---

## 📦 Installation

### 1. Install the Package

```bash
composer require codemystify/laravel-types-generator
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=types-generator-config
```

> **💡 Tip:** The configuration file will be published to `config/types-generator.php`. You can customize paths, exclusions, and generation options there.

---

## ⚡ Quick Start

### Step 1: Annotate Your Resources

Add the `#[GenerateTypes]` attribute to your Resource methods:

```php
<?php

use Codemystify\TypesGenerator\Attributes\GenerateTypes;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    #[GenerateTypes(name: 'User', group: 'auth')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### Step 2: Generate Types

```bash
php artisan generate:types
```

### Step 3: Use in Your Frontend

```typescript
import type { User } from '@/types/generated';

// Full type safety and IntelliSense!
const user: User = {
    id: 1,
    name: 'John Doe',
    email: 'john@example.com',
    avatar: 'https://example.com/avatar.jpg',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
};
```

> **🎉 That's it!** Your TypeScript types are now automatically generated and always in sync with your Laravel backend.

---

## ✨ Features

<table>
<tr>
<td width="50%">

### 🤖 Automatic Generation
- ✅ Generates from Resource `toArray()` methods
- ✅ Supports nested objects and arrays  
- ✅ Handles nullable fields automatically
- ✅ Groups types by category

### 🧠 Smart Detection
- ✅ Analyzes actual return values
- ✅ Supports complex nested structures
- ✅ Handles Laravel's `$this->when()` conditionals
- ✅ Creates separate types for nested objects

</td>
<td width="50%">

### ⚡ Performance & Caching
- ✅ Built-in intelligent caching
- ✅ Validates changes before regenerating
- ✅ Configurable cache TTL
- ✅ Memory-efficient processing

### 🎛️ Flexible Configuration
- ✅ Customize output paths and filenames
- ✅ Control inclusions/exclusions
- ✅ Set generation options
- ✅ Configure validation rules

</td>
</tr>
</table>

---

## 🔧 Command Reference

### Basic Commands

```bash
# Generate all types
php artisan generate:types

# Generate specific group only
php artisan generate:types --group=auth

# Force regeneration (ignores cache)
php artisan generate:types --force

# Dry run - see what would be generated
php artisan generate:types --dry-run

# Verbose output for debugging
php artisan generate:types --verbose
```

### Command Options

| Option | Description | Example |
|--------|-------------|---------|
| `--group=<name>` | Generate specific group only | `--group=auth` |
| `--force` | Force regeneration, ignores cache | `--force` |
| `--dry-run` | Preview what would be generated | `--dry-run` |
| `--verbose` | Show detailed output | `--verbose` |

> **⚠️ Important:** Use `--force` when you've made significant changes to your Resource structure.

---

## 🚀 Advanced Usage

### Complex Nested Types

Here's how to handle complex, deeply nested data structures:

```php
<?php

class EventResource extends JsonResource
{
    #[GenerateTypes(name: 'Event', group: 'events')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            
            // Nested organizer object
            'organizer' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'contact' => [
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ],
            ],
            
            // Array of nested objects
            'tickets' => $this->tickets->map(fn($ticket) => [
                'id' => $ticket->id,
                'type' => $ticket->type,
                'price' => $ticket->price,
                'quantity_available' => $ticket->quantity_available,
            ]),
            
            // Conditional fields
            'private_notes' => $this->when(
                $request->user()?->can('view-private-notes'),
                $this->private_notes
            ),
        ];
    }
}
```

**Generated TypeScript:**

```typescript
export interface Event {
    id: number;
    title: string;
    description: string | null;
    organizer: OrganizerType;
    tickets: TicketType[];
    private_notes?: string;
}

export interface OrganizerType {
    id: number;
    name: string;
    contact: ContactType;
}

export interface ContactType {
    email: string;
    phone: string | null;
}

export interface TicketType {
    id: number;
    type: string;
    price: number;
    quantity_available: number;
}
```

### Controller Methods

Generate types from Controller methods that return JSON:

```php
<?php

class DashboardController extends Controller
{
    #[GenerateTypes(name: 'DashboardStats', group: 'dashboard')]
    public function stats(): JsonResponse
    {
        return response()->json([
            'summary' => [
                'total_users' => User::count(),
                'active_users' => User::where('active', true)->count(),
                'growth_rate' => 12.5,
            ],
            'revenue' => [
                'total' => Order::sum('total'),
                'this_month' => Order::thisMonth()->sum('total'),
                'last_month' => Order::lastMonth()->sum('total'),
            ],
            'recent_orders' => OrderResource::collection(
                Order::latest()->take(5)->get()
            ),
            'top_products' => ProductResource::collection(
                Product::orderBy('sales_count', 'desc')->take(10)->get()
            ),
        ]);
    }
}
```

### Type Groups

Organize your types into logical groups for better maintainability:

```php
// Authentication types → generates auth.ts
#[GenerateTypes(name: 'User', group: 'auth')]
#[GenerateTypes(name: 'LoginResponse', group: 'auth')]

// E-commerce types → generates commerce.ts  
#[GenerateTypes(name: 'Product', group: 'commerce')]
#[GenerateTypes(name: 'Order', group: 'commerce')]

// Analytics types → generates analytics.ts
#[GenerateTypes(name: 'DashboardStats', group: 'analytics')]
#[GenerateTypes(name: 'SalesReport', group: 'analytics')]
```

> **📁 File Organization:** Each group creates a separate TypeScript file, making it easy to import only what you need.

---

## ⚙️ Configuration

The configuration file (`config/types-generator.php`) gives you complete control:

### Output Configuration

```php
'output' => [
    'path' => resource_path('js/types/generated'),
    'filename_pattern' => '{group}.ts',
    'index_file' => true, // Creates index.ts with all exports
    'backup_old_files' => true,
    'create_directories' => true,
],
```

### Source Paths

```php
'sources' => [
    'resources_path' => app_path('Http/Resources'),
    'controllers_path' => app_path('Http/Controllers'),
    'models_path' => app_path('Models'),
    'migrations_path' => database_path('migrations'),
],
```

### Generation Options

```php
'generation' => [
    'include_comments' => true,
    'include_readonly' => true,
    'include_optional_fields' => true,
    'strict_types' => true,
    'extract_nested_types' => true,
],
```

### Exclusions

```php
'exclude' => [
    'methods' => [
        'password',
        'remember_token',
        'email_verified_at',
    ],
    'classes' => [
        // Add classes to exclude
    ],
    'patterns' => [
        '/test/i',
        '/mock/i',
    ],
],
```

### Performance Settings

```php
'performance' => [
    'cache_enabled' => env('TYPES_GENERATOR_CACHE', true),
    'cache_ttl' => 3600, // 1 hour
    'parallel_processing' => false,
],
```

> **🔧 Pro Tip:** Set `TYPES_GENERATOR_CACHE=false` in your `.env` during development for immediate regeneration.

---

## 📄 Output Examples

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
    avatar?: string;
    created_at: string;
    updated_at: string;
}
```

### Complex Nested Interface

```typescript
export interface Order {
    id: number;
    order_number: string;
    status: 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
    total: number;
    
    customer: {
        id: number;
        name: string;
        email: string;
    };
    
    items: {
        id: number;
        product_name: string;
        quantity: number;
        unit_price: number;
        total_price: number;
    }[];
    
    shipping_address: {
        street: string;
        city: string;
        state: string;
        postal_code: string;
        country: string;
    };
    
    payment?: {
        method: string;
        status: string;
        transaction_id?: string;
    };
}
```

### Index File (Auto-generated)

When `index_file` is enabled in config:

```typescript
// resources/js/types/generated/index.ts

// Re-export all generated types for easy importing
export * from './auth';
export * from './commerce';
export * from './analytics';
export * from './dashboard';

// You can now import like this:
// import { User, Product, Order } from '@/types/generated';
```

---

## 🎯 Best Practices

### 1. 📝 Use Descriptive Type Names

```php
// ❌ Avoid generic names
#[GenerateTypes(name: 'User', group: 'users')]

// ✅ Be specific about the context
#[GenerateTypes(name: 'UserProfile', group: 'users')]
#[GenerateTypes(name: 'UserListItem', group: 'users')]
#[GenerateTypes(name: 'UserDashboardStats', group: 'users')]
```

### 2. 📂 Group Related Types Logically

```php
// ✅ Group by feature/domain
#[GenerateTypes(group: 'auth')]        // Authentication
#[GenerateTypes(group: 'commerce')]    // E-commerce
#[GenerateTypes(group: 'analytics')]   // Analytics/reporting
#[GenerateTypes(group: 'messaging')]   // Chat/notifications
```

### 3. 📖 Document Your Types

```php
#[GenerateTypes(
    name: 'ProductWithReviews',
    group: 'commerce',
    description: 'Complete product data including reviews, variants, and inventory'
)]
```

### 4. 🔄 Handle Nullable Fields Properly

```php
public function toArray($request): array
{
    return [
        'id' => $this->id,                    // number
        'title' => $this->title,              // string
        'description' => $this->description,  // string | null (automatically detected)
        'published_at' => $this->when(        // string | undefined (conditional)
            $this->published_at,
            $this->published_at
        ),
    ];
}
```

### 5. 🧪 Test Your Generated Types

```typescript
// Create test files to validate your types
import type { User, Product, Order } from '@/types/generated';

// This will catch type errors at compile time
const testUser: User = {
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
};
```

### 6. 🔄 Automate Generation in CI/CD

Add to your deployment pipeline:

```yaml
# .github/workflows/deploy.yml
- name: Generate TypeScript Types
  run: |
    php artisan generate:types --force
    git add resources/js/types/generated/
    git commit -m "Update generated types" || true
```

---

## 🔍 Troubleshooting

### Types Not Generating?

> **✅ Checklist:**
> - Ensure you have the `#[GenerateTypes]` attribute on your methods
> - Verify the method returns an array (not a collection or object)
> - Check your source paths in `config/types-generator.php`
> - Make sure the method is public

```bash
# Debug what's being scanned
php artisan generate:types --verbose --dry-run
```

### Cache Issues?

> **🧹 Clear the cache:**
```bash
php artisan generate:types --force
```

> **🔧 Disable cache during development:**
```bash
# Add to your .env
TYPES_GENERATOR_CACHE=false
```

### Complex Nested Types Not Working?

> **🔍 The package analyzes actual return values, not database schemas.**

Make sure your Resource methods return consistent structures:

```php
// ❌ Inconsistent structure
public function toArray($request): array
{
    $data = ['id' => $this->id];
    
    if (some_condition()) {
        $data['extra_field'] = 'value';
    }
    
    return $data; // Structure varies!
}

// ✅ Consistent structure
public function toArray($request): array
{
    return [
        'id' => $this->id,
        'extra_field' => $this->when(some_condition(), 'value'),
    ];
}
```

### Permission Errors?

> **📁 Check directory permissions:**
```bash
# Make sure Laravel can write to the output directory
chmod -R 755 resources/js/types/
```

### No Output Files?

> **🔧 Check your configuration:**

1. Verify output path exists and is writable
2. Check if any exclusion patterns are too broad
3. Ensure at least one Resource has the `#[GenerateTypes]` attribute

```bash
# Test with a simple example first
php artisan generate:types --group=test --verbose
```

---

## 📋 Requirements

<table>
<tr>
<td width="50%">

### ✅ System Requirements
- **PHP:** 8.2 or higher
- **Laravel:** 11.0+ or 12.0+
- **Composer:** 2.0 or higher

</td>
<td width="50%">

### 📦 Dependencies
- `illuminate/support`: ^11.0
- `illuminate/console`: ^11.0  
- `illuminate/filesystem`: ^11.0
- `nikic/php-parser`: ^4.15|^5.0

</td>
</tr>
</table>

> **⚠️ Laravel Version Support:** This package supports Laravel 11+ and Laravel 12+. For Laravel 10 and below, please use an earlier version of this package.

---

## 🧪 Testing

### Run All Tests

```bash
composer test
```

### Run Specific Test Suites

```bash
# Unit tests only
composer test:unit

# Feature tests only  
composer test:feature

# Generate coverage report
composer test:coverage
```

### Test Commands

```bash
# Check code style
composer format:test

# Fix code style
composer format

# Run quality checks
composer quality
```

> **🎯 Test Coverage:** This package maintains high test coverage to ensure reliability across different Laravel and PHP versions.

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### 🐛 Bug Reports

Found a bug? Please [open an issue](https://github.com/codemystify/laravel-types-generator/issues) with:
- Laravel version
- PHP version  
- Package version
- Steps to reproduce
- Expected vs actual behavior

### 💡 Feature Requests

Have an idea? [Start a discussion](https://github.com/codemystify/laravel-types-generator/discussions) to:
- Explain the use case
- Provide examples
- Discuss implementation approaches

### 🔧 Development Setup

```bash
# Clone the repository
git clone https://github.com/codemystify/laravel-types-generator.git

# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer format:test
```

> **📖 More Details:** See [CONTRIBUTING.md](CONTRIBUTING.md) for detailed contribution guidelines.

---

## 📄 License

This package is open-sourced software licensed under the [MIT License](LICENSE).

---

<div align="center">

**Made with ❤️ by [CodeMystify](https://codemystify.com)**

[![GitHub](https://img.shields.io/badge/GitHub-codemystify/laravel--types--generator-blue.svg)](https://github.com/codemystify/laravel-types-generator)
[![Packagist](https://img.shields.io/badge/Packagist-codemystify/laravel--types--generator-orange.svg)](https://packagist.org/packages/codemystify/laravel-types-generator)

**⭐ If this package helped you, please give it a star!**

</div>
