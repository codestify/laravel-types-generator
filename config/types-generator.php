<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */
    'output' => [
        'path' => base_path('resources/js/types/generated'),
        'filename_pattern' => '{group}.ts',
        'index_file' => true,
        'backup_old_files' => true,
        'create_directories' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Paths
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'resources_path' => base_path('app/Http/Resources'),
        'controllers_path' => base_path('app/Http/Controllers'),
        'models_path' => base_path('app/Models'),
        'migrations_path' => base_path('database/migrations'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    */
    'namespaces' => [
        'resources' => 'App\\Http\\Resources',
        'controllers' => 'App\\Http\\Controllers',
        'models' => 'App\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Generation Options
    |--------------------------------------------------------------------------
    */
    'generation' => [
        'include_comments' => true,
        'include_readonly' => true,
        'include_optional_fields' => true,
        'strict_types' => true,
        'enum_as_const' => false,
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
        'include_json_fields' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance & Caching
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_enabled' => env('TYPES_GENERATOR_CACHE', true),
        'cache_ttl' => 3600, // 1 hour
        'parallel_processing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    */
    'exclude' => [
        'methods' => [
            'password',
            'remember_token',
            'email_verified_at',
            'created_at',
            'updated_at',
        ],
        'classes' => [
            // Add classes to exclude
        ],
        'files' => [
            // Add files to exclude
        ],
        'patterns' => [
            '/test/i',
            '/mock/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Debugging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('TYPES_GENERATOR_LOG', false),
        'level' => 'info',
        'channel' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'strict_mode' => true,
        'require_return_types' => true,
        'validate_structures' => true,
    ],
];
