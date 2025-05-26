# Laravel TypeScript Types Generator

<div align="center">

**🚀 Generate TypeScript types directly from your Laravel Resources and Controllers**

[![Latest Version](https://img.shields.io/packagist/v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![PHP Version](https://img.shields.io/packagist/php-v/codemystify/laravel-types-generator)](https://packagist.org/packages/codemystify/laravel-types-generator)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%2B%20%7C%2012%2B-red.svg)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/codemystify/laravel-types-generator)](LICENSE)

</div>

---

## 💡 Why This Package?

Automatically generate TypeScript types from your Laravel API responses. No more manual type definitions, no more sync issues.

**Key Benefits:**
- 🔄 **Always in sync** - Types generated from actual code
- 🚀 **Zero maintenance** - No manual type definitions
- 🛡️ **Type safety** - Catch errors at compile time
- ⚡ **Developer experience** - Full IntelliSense and autocomplete

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

class EventResource extends JsonResource
{
    #[GenerateTypes(name: 'Event', group: 'events')]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'is_featured' => $this->is_featured,
            'organization' => $this->organization,
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
import type { Event } from '@/types/generated';

const EventCard: React.FC<{ event: Event }> = ({ event }) => {
    return (
        <div>
            <h2>{event.title}</h2>
            <p>{event.description}</p>
            {event.is_featured && <span>Featured</span>}
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
│ ├─ EventResource │    │ ├─ EventController│    │ ├─ Event.php    │
│ ├─ UserResource  │    │ ├─ UserController │    │ ├─ User.php     │
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
│  │ • events.ts     │           │ • TypeScript    │              │
│  │ • users.ts      │           │   interfaces    │              │
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
│  ├─ events.ts     ──► export interface Event { ... }           │
│  ├─ users.ts      ──► export interface User { ... }            │
│  ├─ default.ts    ──► export interface Other { ... }           │
│  └─ index.ts      ──► export * from './events'; ...            │
└─────────────────────────────────────────────────────────────────┘
```

**Process:**
1. **Discovery**: Scans for `#[GenerateTypes]` attributes in Resources/Controllers
2. **Analysis**: Uses AST parsing, reflection, and database schema analysis
3. **Generation**: Creates TypeScript interfaces with proper types
4. **Output**: Organized files by group with index exports

---

## ✨ Features

- **Attribute-based**: Use `#[GenerateTypes]` to mark methods for type generation
- **Smart Analysis**: Combines AST parsing, reflection, and database schema analysis
- **Type Groups**: Organize types into separate files (`events.ts`, `users.ts`, etc.)
- **Environment Support**: Configure paths via environment variables
- **Caching**: Built-in caching for faster subsequent generations
- **Laravel Patterns**: Understands Laravel conventions and relationships
- **Nested Types**: Automatically extracts nested object structures
- **Error Handling**: Graceful fallbacks for complex scenarios

---

## 🎯 Commands

```bash
# Generate all types
php artisan generate:types

# Generate specific group only
php artisan generate:types --group=events

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
 * Event resource data structure
 * @source App\Http\Resources\EventResource::toArray
 * @group events
 */
export interface Event {
  readonly id: string;
  readonly title: string;
  readonly description?: string;
  readonly start_date: string;
  readonly is_featured?: boolean;
  readonly organization?: OrganizationType;
}

export interface OrganizationType {
  readonly id: string;
  readonly name: string;
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
