# Laravel Types Generator

I got tired of manually writing TypeScript types for my Laravel APIs, so I built this. It's simple: you tell it exactly what your data looks like, and it generates clean TypeScript interfaces. No magic, no guessing, just straight-forward type generation.

## What This Actually Does

You add an attribute to your Laravel classes (like API resources), define the structure, run a command, and get TypeScript files. That's it.

## Installation

```bash
composer require codemystify/laravel-types-generator
```

If you want to customize the config:
```bash
php artisan vendor:publish --tag=types-generator-config
```

## Quick Example

Here's how I use it in my Laravel API resources:

```php
use Codemystify\TypesGenerator\Attributes\GenerateTypes;

class UserResource extends JsonResource
{
    #[GenerateTypes(
        name: 'User',
        structure: [
            'id' => 'number',
            'name' => 'string',
            'email' => 'string',
            'avatar' => ['type' => 'string', 'optional' => true],
            'created_at' => 'string',
        ]
    )]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

Run the command:
```bash
php artisan types:generate
```

Get this TypeScript file:
```typescript
// user.ts
export interface User {
  id: number;
  name: string;
  email: string;
  avatar?: string;
  created_at: string;
}
```

## How to Define Types

### Basic Types
```php
[
    'title' => 'string',
    'count' => 'number', 
    'active' => 'boolean',
    'data' => 'any',        // Use sparingly
]
```

### Optional Fields
For fields that might not be present:
```php
[
    'bio' => ['type' => 'string', 'optional' => true],  // bio?: string
]
```

### Nullable Fields
For fields that can be null:
```php
[
    'deleted_at' => ['type' => 'string', 'nullable' => true],  // deleted_at: string | null
]
```

### Arrays
```php
[
    'tags' => 'string[]',     // Array of strings
    'users' => 'User[]',      // Array of User interfaces
]
```

### Union Types
```php
[
    'status' => 'string|null',  // status: string | null
]
```

## Real Example: Blog Post API

Here's how I handle a typical blog post resource:

```php
class PostResource extends JsonResource
{
    #[GenerateTypes(
        name: 'Post',
        structure: [
            'id' => 'number',
            'title' => 'string',
            'slug' => 'string',
            'content' => 'string',
            'excerpt' => ['type' => 'string', 'nullable' => true],
            'published' => 'boolean',
            'author' => 'Author',
            'tags' => 'string[]',
            'created_at' => 'string',
            'updated_at' => 'string',
        ],
        types: [
            'Author' => [
                'id' => 'number',
                'name' => 'string',
                'email' => 'string',
                'avatar' => ['type' => 'string', 'optional' => true],
            ]
        ]
    )]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'published' => $this->published,
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'avatar' => $this->user->avatar,
            ],
            'tags' => $this->tags->pluck('name')->toArray(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

This generates two clean interfaces:

```typescript
// post.ts
import type { Author } from './author';

export interface Post {
  id: number;
  title: string;
  slug: string;
  content: string;
  excerpt: string | null;
  published: boolean;
  author: Author;
  tags: string[];
  created_at: string;
  updated_at: string;
}

export interface Author {
  id: number;
  name: string;
  email: string;
  avatar?: string;
}
```

## Commands

### Generate Types
```bash
php artisan types:generate
```

### Preview Without Writing Files
```bash
php artisan types:generate --dry-run
```

### Generate Specific Group
```bash
php artisan types:generate --group=api
```

## Using Groups

I organize my types with groups:

```php
#[GenerateTypes(
    name: 'AdminUser',
    structure: [...],
    group: 'admin'
)]

#[GenerateTypes(
    name: 'PublicPost',
    structure: [...],
    group: 'public'
)]
```

Then generate specific groups:
```bash
php artisan types:generate --group=admin
```

## File Organization

All generated files go to `resources/js/types/generated/` by default:

```
resources/js/types/generated/
├── index.ts          # Exports everything
├── user.ts
├── post.ts
├── admin-user.ts
└── ...
```

The `index.ts` file automatically exports everything:
```typescript
export * from './user';
export * from './post';
export * from './admin-user';
```

So in your React/Vue components:
```typescript
import { User, Post } from '@/types/generated';
```

## Configuration

The defaults work fine, but you can customize:

```php
// config/types-generator.php
return [
    'sources' => [
        'app/Http/Resources',
        'app/Http/Controllers',
        'app/Models',
    ],

    'output' => [
        'base_path' => 'resources/js/types/generated',
    ],

    'files' => [
        'extension' => 'ts',
        'naming_pattern' => 'kebab-case',
        'add_header_comment' => true,
    ],
];
```

## Practical Tips

### 1. Start Simple
Don't try to define everything at once. Start with basic types and add complexity as needed.

### 2. Mirror Your API Exactly
The structure should match exactly what your API returns. Don't overthink it.

### 3. Use Optional vs Nullable Correctly
- `optional: true` - field might not exist in the response
- `nullable: true` - field exists but can be null

### 4. Handle Pagination
```php
#[GenerateTypes(
    name: 'PaginatedPosts',
    structure: [
        'data' => 'Post[]',
        'meta' => 'PaginationMeta',
    ],
    types: [
        'PaginationMeta' => [
            'current_page' => 'number',
            'last_page' => 'number',
            'per_page' => 'number',
            'total' => 'number',
        ]
    ]
)]
```

### 5. Keep It DRY with Shared Types
Define common types once and reuse them:

```php
// In a base resource or dedicated class
'address' => 'Address',

types: [
    'Address' => [
        'street' => 'string',
        'city' => 'string',
        'country' => 'string',
        'postal_code' => 'string',
    ]
]
```

## Why I Built This

I tried other solutions but they were either:
- Too magic (trying to guess types from code)
- Too complicated (requiring tons of configuration)
- Too unreliable (breaking when Laravel code changed)

This approach is explicit and predictable. You define exactly what you want, and you get exactly that. No surprises.

## Troubleshooting

### Types not generating?
- Make sure you're using the attribute in classes that the scanner can find
- Check that your `sources` config includes the right directories
- Run with `--dry-run` to see what would be generated

### Import errors in TypeScript?
- The generator creates proper import statements automatically
- Make sure you're importing from the right path
- Check that the `index.ts` file was generated

### Want to disable the package in production?
The attributes have no runtime impact, but if you want to remove them:
```bash
php artisan types:cleanup --remove-attributes
```

That's it! Simple, predictable TypeScript type generation for Laravel. No magic, just the types you define.
