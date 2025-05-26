<?php

namespace Codemystify\TypesGenerator\Tests;

use Codemystify\TypesGenerator\Providers\TypesGeneratorServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/types-generator-tests-'.uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
        $this->setupTestConfig();
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [TypesGeneratorServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function setupTestConfig(): void
    {
        config()->set('types-generator', [
            'output' => [
                'path' => $this->tempDir.'/output',
                'filename_pattern' => '{group}.ts',
                'index_file' => true,
                'backup_old_files' => false,
            ],
            'sources' => [
                'resources_path' => $this->tempDir.'/resources',
                'controllers_path' => $this->tempDir.'/controllers',
                'models_path' => $this->tempDir.'/models',
                'migrations_path' => $this->tempDir.'/migrations',
            ],
            'namespaces' => [
                'resources' => 'App\\Http\\Resources',
                'controllers' => 'App\\Http\\Controllers',
                'models' => 'App\\Models',
            ],
            'generation' => [
                'include_comments' => true,
                'include_readonly' => true,
                'strict_types' => true,
            ],
            'performance' => [
                'cache_enabled' => false,
            ],
            'validation' => [
                'strict_mode' => false,
                'validate_structures' => true,
            ],
        ]);
    }
}
