<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TypeScript Output Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where the generated TypeScript files should be placed. All
    | generated types will be placed in the base_path directory without
    | file type separation folders.
    |
    */

    'output' => [
        // Base directory for all generated TypeScript files
        'base_path' => 'resources/js/types',
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Code Scanning Paths
    |--------------------------------------------------------------------------
    |
    | Directories to scan when looking for classes with #[GenerateTypes]
    | attributes. The package will recursively search these directories
    | for PHP files containing the attribute.
    |
    */

    'sources' => [
        'app/Http/Resources',
        'app/Http/Controllers',
        'app/Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Intelligent Type Aggregation
    |--------------------------------------------------------------------------
    |
    | Configure how the package intelligently analyzes and extracts common
    | patterns from your types. The aggregator will find similar structures
    | and extract them into a commons file automatically.
    |
    */

    'aggregation' => [
        // Similarity threshold (0.0 to 1.0) for detecting similar types
        'similarity_threshold' => 0.75,

        // Minimum number of types needed to create a common type
        'minimum_occurrence' => 2,

        // Enable intelligent common type extraction
        'extract_common_types' => true,

        // Common types file name (without extension)
        'commons_file_name' => 'common',

        // Index file name (without extension)
        'index_file_name' => 'index',

        // Property pattern similarity threshold for grouping
        'property_similarity_threshold' => 0.6,

        // Minimum property count for common type creation
        'minimum_common_properties' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | TypeScript Primitive Types
    |--------------------------------------------------------------------------
    |
    | List of TypeScript primitive types that are recognized by the generator.
    | These types will be used as-is without any transformation. You can add
    | custom primitive types here if needed.
    |
    */

    'primitive_types' => [
        'string',
        'number',
        'boolean',
        'any',
        'unknown',
        'void',
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Generation Options
    |--------------------------------------------------------------------------
    |
    | Fine-tune how the TypeScript code is generated. These options affect
    | the format and structure of the output files.
    |
    */

    'generation' => [
        // Whether to export interfaces (true) or use type aliases (false)
        'export_interfaces' => true,

        // Generate index file that exports all types
        'generate_index' => true,

        // Handle name collisions automatically
        'handle_collisions' => true,

        // Include comments in generated types for documentation
        'include_comments' => true,

        // Include readonly modifier for immutable properties
        'include_readonly' => false,

        // Use strict TypeScript types (avoid 'any' where possible)
        'strict_types' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Organization
    |--------------------------------------------------------------------------
    |
    | Configure how generated files are organized and named.
    |
    */

    'files' => [
        // File extension for generated files (without dot)
        'extension' => 'ts',

        // Naming pattern for generated files: 'kebab-case', 'camelCase', 'PascalCase', 'snake_case'
        'naming_pattern' => 'kebab-case',

        // Backup old files before overwriting
        'backup_old_files' => false,

        // Add header comment to generated files
        'add_header_comment' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching and performance optimizations.
    |
    */

    'performance' => [
        // Enable caching of parsed structures for faster regeneration
        'cache_enabled' => false,

        // Cache TTL in seconds (only used when cache_enabled is true)
        'cache_ttl' => 3600,

        // Maximum number of files to process in parallel
        'max_parallel_files' => 10,
    ],

];
