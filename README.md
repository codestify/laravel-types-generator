# Laravel TypeScript Types Generator

<div align="center">

**🚀 Generate TypeScript types directly from your Laravel Resources and Controllers**

*Domain-agnostic • Pattern-based • Zero configuration required*

[![Latest Version](https://img.shields.io/packagist/v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![PHP Version](https://img.shields.io/packagist/php-v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012%2B-red.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/codemystify/laravel-types-generator)](LICENSE)

</div>

---

## 💡 Why This Package?

Automatically generate TypeScript types from your Laravel API responses. **Works with any Laravel project** - e-commerce, CRM, blog, SaaS, or custom applications.

**Key Benefits:**
- 🔄 **Always in sync** - Types generated from actual code
- 🚀 **Zero maintenance** - No manual type definitions
- 🛡️ **Type safety** - Catch errors at compile time
- ⚡ **Developer experience** - Full IntelliSense and autocomplete
- 🎯 **Domain-agnostic** - Works with any Laravel project out of the box
- 🧠 **Smart pattern detection** - Understands Laravel conventions automatically

---

## 📦 Installation

```bash
# Install the package
composer require codemystify/laravel-types-generator

# Publish configuration
php artisan vendor:publish --tag=types-generator-config

# Create output directory
mkdir -p resources/js/types/generated
```

---
## ⚡ Quick Start

### 1. Annotate Your Resources

```php
<?php

use Codemystify\TypesGenerator\Attributes\GenerateTypes;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    #[GenerateTypes(name: 'User', group: 'users')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
            'is_active' => $this->is_active,
            'profile' => $this->whenLoaded('profile', function () {
                return [
                    'bio' => $this->profile->bio,
                    'avatar' => $this->profile->avatar_url,
                ];
            }),
        ];
    }
}
```

### 2. Generate Types

```bash
php artisan generate:types
```

### 3. Use in Frontend

```typescript
import type { User } from '@/types/generated';

const UserCard: React.FC<{ user: User }> = ({ user }) => {
    return (
        <div>
            <h2>{user.name}</h2>
            <p>{user.email}</p>
            {user.is_active && <span>Active</span>}
            {user.profile && (
                <div>
                    <p>{user.profile.bio}</p>
                    <img src={user.profile.avatar} alt="Avatar" />
                </div>
            )}
        </div>
    );
};
```

---

## 🔄 How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│                    LARAVEL TYPES GENERATOR                     │
└─────────────────────────────────────────────────────────────────┘

1. DISCOVERY PHASE
┌──────────────────┐    ┌──────────────────┐    ┌──────────────────┐
│   App/Http/      │    │   App/Http/      │    │   App/Models/    │
│   Resources/     │◄──►│   Controllers/   │◄──►│   (Schema Info)  │
│                  │    │                  │    │                  │
│ ├─ UserResource  │    │ ├─ UserController │    │ ├─ User.php     │
│ ├─ PostResource  │    │ ├─ PostController │    │ ├─ Post.php     │
│ └─ ...           │    │ └─ ...           │    │ └─ ...          │
└──────────────────┘    └──────────────────┘    └──────────────────┘
           │                        │                        │
           │                        │                        │
           ▼                        ▼                        ▼
     Scan for #[GenerateTypes] attributes + Analyze DB Schema

2. ANALYSIS PHASE
┌─────────────────────────────────────────────────────────────────┐
│                    TypeGeneratorService                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ SimpleReflection│  │ AstAnalyzer     │  │ MigrationAnalyzer│ │
│  │ Analyzer        │  │                 │  │                 │ │
│  │                 │  │ • Parse PHP AST │  │ • Read migrations│ │
│  │ • Invoke methods│  │ • Extract types │  │ • Build schema  │ │
│  │ • Analyze output│  │ • Handle complex│  │ • Map DB types  │ │
│  │ • Pattern match │  │   expressions   │  │   to TS types   │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
                     Combine & Process Types

3. GENERATION PHASE
┌─────────────────────────────────────────────────────────────────┐
│                   TypeScriptGenerator                          │
│                                                                 │
│  ┌─────────────────┐           ┌─────────────────┐              │
│  │ Group Types     │    ──►    │ Generate Files  │              │
│  │ • users.ts      │           │ • TypeScript    │              │
│  │ • posts.ts      │           │   interfaces    │              │
│  │ • default.ts    │           │ • Comments      │              │
│  └─────────────────┘           │ • Exports       │              │
│                                └─────────────────┘              │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
4. OUTPUT PHASE
┌─────────────────────────────────────────────────────────────────┐
│            resources/js/types/generated/                       │
│                                                                 │
│  ├─ users.ts      ──► export interface User { ... }            │
│  ├─ posts.ts      ──► export interface Post { ... }            │
│  ├─ default.ts    ──► export interface Other { ... }           │
│  └─ index.ts      ──► export * from './users'; ...             │
└─────────────────────────────────────────────────────────────────┘
```

**Process:**
1. **Discovery**: Scans for `#[GenerateTypes]` attributes in Resources/Controllers
2. **Analysis**: Uses AST parsing, reflection, and database schema analysis
3. **Generation**: Creates TypeScript interfaces with proper types
4. **Output**: Organized files by group with index exports

---

## ✨ Features

### Core Capabilities
- **Domain-Agnostic**: Works with any Laravel project (e-commerce, CRM, blog, etc.)
- **Attribute-Based**: Use `#[GenerateTypes]` to mark methods for type generation
- **Smart Pattern Detection**: Automatically recognizes Laravel conventions without configuration

### Analysis Engine
- **AST Parsing**: Deep code analysis for complex expressions and closures
- **Database Schema**: Integrates with migrations for accurate property types
- **Relationship Detection**: Automatically handles `whenLoaded()` and Laravel relationships
- **Method Tracing**: Follows method calls and trait usage for complete type inference

### Output & Organization
- **Nested Types**: Automatically extracts and names nested object structures
- **TypeScript Standards**: Generates clean, readable interfaces with proper documentation
- **Index Exports**: Creates convenient barrel exports for easy importing

### Developer Experience
- **Environment Support**: Configure paths via environment variables
- **Caching**: Built-in caching for faster subsequent generations
- **Error Handling**: Graceful fallbacks for complex scenarios
- **Laravel Patterns**: Understands common patterns like enums, dates, and nullable fields

---

## 🎯 Smart Pattern Detection

The package automatically recognizes common Laravel patterns without any configuration:

### Property Patterns
```php
// Automatically detected as string
'id', 'uuid', 'ulid', 'title', 'name', 'description', 'slug', 'email'

// Automatically detected as boolean  
'is_active', 'is_featured', 'has_permission', 'can_edit'

// Automatically detected as number
'price', 'amount', 'total', 'count', 'quantity'

// Automatically detected as date strings
'created_at', 'updated_at', 'published_at', 'start_date'
```

### Relationship Patterns
```php
// Category relationships (any field containing 'category')
'category', 'event_category', 'post_category'
// → { id: string; name: string; slug: string; }

// Image/Media relationships (any field containing 'image') 
'cover_image', 'avatar', 'banner', 'profile_image'
// → { url: string; alt_text?: string; }

// User relationships (any field containing 'user')
'user', 'created_by', 'assigned_user'
// → { id: string; name: string; email: string; }
```

### Method Patterns
```php
// Address/location methods
getFormattedAddress(), getFullAddress()
// → string | null

// Management data methods  
getManageUserData(), getManagementData(), getDataForManagement()
// → Generic object structure

// Analytics methods
getStats(), calculateAnalytics(), getMetrics()
// → { total: number; count: number; percentage: number; }
```

### Laravel Conventions
- **Enums**: Automatically detects `->value` access and ternary enum patterns
- **Dates**: Recognizes `->toISOString()`, `->format()` method calls
- **whenLoaded()**: Properly analyzes closure returns for relationship data
- **Nullable**: Smart detection of nullable fields based on context

---

## 🎯 Commands

```bash
# Generate all types
php artisan generate:types

# Generate specific group only
php artisan generate:types --group=users

# Force regeneration (ignores cache)
php artisan generate:types --force

# Watch for changes (coming soon)
php artisan generate:types --watch
```

---

## ⚙️ Configuration

The `config/types-generator.php` file controls all package behavior:

### Output Configuration
```php
'output' => [
    'path' => env('TYPES_GENERATOR_OUTPUT_PATH', base_path('resources/js/types/generated')),
    'filename_pattern' => env('TYPES_GENERATOR_FILENAME_PATTERN', '{group}.ts'),
    'index_file' => env('TYPES_GENERATOR_INDEX_FILE', true),
],
```

### Source Paths
```php
'sources' => [
    'resources_path' => env('TYPES_GENERATOR_RESOURCES_PATH', base_path('app/Http/Resources')),
    'controllers_path' => env('TYPES_GENERATOR_CONTROLLERS_PATH', base_path('app/Http/Controllers')),
    'models_path' => env('TYPES_GENERATOR_MODELS_PATH', base_path('app/Models')),
],
```

---

## 📄 Output Example

**Generated TypeScript:**
```typescript
/**
 * Auto-generated TypeScript types
 * Generated at: 2025-01-26T12:00:00.000Z
 * DO NOT EDIT MANUALLY - This file is auto-generated
 */

/**
 * User resource data structure
 * @source App\Http\Resources\UserResource::toArray
 * @group users
 */
export interface User {
  readonly id: string;
  readonly name: string;
  readonly email: string;
  readonly created_at: string;
  readonly is_active: boolean;
  readonly profile: ProfileType | null;
}

/**
 * Profile data structure
 * @source extracted_nested_type
 * @group users
 */
export interface ProfileType {
  readonly bio: string;
  readonly avatar: string;
}
```

---

## 🔧 Environment Variables

Customize paths without modifying config files:

```bash
# .env
TYPES_GENERATOR_OUTPUT_PATH="/custom/output/path"
TYPES_GENERATOR_FILENAME_PATTERN="{group}.types.ts"
TYPES_GENERATOR_RESOURCES_PATH="/app/Http/Resources"
TYPES_GENERATOR_CONTROLLERS_PATH="/app/Http/Controllers"
```

---

## 📋 Requirements

- **PHP**: 8.2+
- **Laravel**: 11.0+ | 12.0+
- **Dependencies**: 
  - `nikic/php-parser` for AST analysis
  - Laravel's core packages

---

## 🧪 Testing

```bash
# Run all tests
./run-tests.sh

# Run specific test suites
./run-tests.sh --unit
./run-tests.sh --feature

# Generate coverage report
./run-tests.sh --coverage
```

---

## 📝 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

---

<div align="center">
<strong>Made with ❤️ by <a href="https://codemystify.com">CodeMystify</a></strong>
</div>
