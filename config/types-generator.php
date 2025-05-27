<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */
    'output' => [
        'path' => env('TYPES_GENERATOR_OUTPUT_PATH', 'resources/js/types/generated'),
        'filename_pattern' => '{group}.ts',
        'index_file' => true,
        'backup_old_files' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Paths (relative to project root)
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'resources_path' => env('TYPES_GENERATOR_RESOURCES_PATH', 'app/Http/Resources'),
        'controllers_path' => env('TYPES_GENERATOR_CONTROLLERS_PATH', 'app/Http/Controllers'),
        'models_path' => env('TYPES_GENERATOR_MODELS_PATH', 'app/Models'),
        'migrations_path' => env('TYPES_GENERATOR_MIGRATIONS_PATH', 'database/migrations'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    */
    'namespaces' => [
        'resources' => env('TYPES_GENERATOR_RESOURCES_NAMESPACE', 'App\\Http\\Resources'),
        'controllers' => env('TYPES_GENERATOR_CONTROLLERS_NAMESPACE', 'App\\Http\\Controllers'),
        'models' => env('TYPES_GENERATOR_MODELS_NAMESPACE', 'App\\Models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Generation Options
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'include_comments' => true,
        'include_readonly' => true,
        'strict_types' => true,
        'extract_nested_types' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Analysis
    |--------------------------------------------------------------------------
    */
    'database' => [
        'analyze_migrations' => true,
        'analyze_models' => true,
        'include_relationships' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Caching
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_enabled' => env('TYPES_GENERATOR_CACHE', true),
        'cache_ttl' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Commons Configuration
    |--------------------------------------------------------------------------
    */
    'commons' => [
        'enabled' => env('TYPES_GENERATOR_COMMONS_ENABLED', true),
        'file_name' => 'common',
        'threshold' => 2,
        'import_style' => 'relative',
        'include_in_index' => true,
    ],
];
