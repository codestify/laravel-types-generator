<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    */
    'output' => [
        'path' => env('TYPES_GENERATOR_OUTPUT_PATH', base_path('resources/js/types/generated')),
        'filename_pattern' => env('TYPES_GENERATOR_FILENAME_PATTERN', '{group}.ts'),
        'index_file' => env('TYPES_GENERATOR_INDEX_FILE', true),
        'backup_old_files' => env('TYPES_GENERATOR_BACKUP', true),
        'create_directories' => env('TYPES_GENERATOR_CREATE_DIRS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Paths
    |--------------------------------------------------------------------------
    */
    'sources' => [
        'resources_path' => env('TYPES_GENERATOR_RESOURCES_PATH', base_path('app/Http/Resources')),
        'controllers_path' => env('TYPES_GENERATOR_CONTROLLERS_PATH', base_path('app/Http/Controllers')),
        'models_path' => env('TYPES_GENERATOR_MODELS_PATH', base_path('app/Models')),
        'migrations_path' => env('TYPES_GENERATOR_MIGRATIONS_PATH', base_path('database/migrations')),
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
