<?php

namespace Codemystify\TypesGenerator\Providers;

use Codemystify\TypesGenerator\Console\GenerateTypesCommand;
use Codemystify\TypesGenerator\Services\CommonTypesExtractor;
use Codemystify\TypesGenerator\Services\MigrationAnalyzer;
use Codemystify\TypesGenerator\Services\SimpleReflectionAnalyzer;
use Codemystify\TypesGenerator\Services\TypeGeneratorService;
use Codemystify\TypesGenerator\Services\TypeReferenceRewriter;
use Codemystify\TypesGenerator\Services\TypeRegistry;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\ServiceProvider;

class TypesGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            $this->getConfigPath(),
            'types-generator'
        );

        // Register new deduplication services
        $this->app->singleton(TypeRegistry::class);
        $this->app->singleton(CommonTypesExtractor::class);
        $this->app->singleton(TypeReferenceRewriter::class);

        $this->app->singleton(TypeGeneratorService::class, function ($app) {
            return new TypeGeneratorService(
                $app->make(SimpleReflectionAnalyzer::class),
                $app->make(MigrationAnalyzer::class),
                $app->make(TypeScriptGenerator::class)
            );
        });

        $this->app->singleton(SimpleReflectionAnalyzer::class);
        $this->app->singleton(MigrationAnalyzer::class);

        $this->app->singleton(TypeScriptGenerator::class, function ($app) {
            return new TypeScriptGenerator(
                $app->make(TypeRegistry::class),
                $app->make(CommonTypesExtractor::class),
                $app->make(TypeReferenceRewriter::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path('types-generator.php'),
            ], 'types-generator-config');

            $this->commands([
                GenerateTypesCommand::class,
            ]);
        }
    }

    public function provides(): array
    {
        return [
            TypeGeneratorService::class,
        ];
    }

    /**
     * Get the path to the package config file
     */
    private function getConfigPath(): string
    {
        return realpath(__DIR__.'/../../config/types-generator.php') ?: __DIR__.'/../../config/types-generator.php';
    }
}
