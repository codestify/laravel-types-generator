<?php

namespace Codemystify\TypesGenerator\Providers;

use Codemystify\TypesGenerator\Console\AnalyzeTypesCommand;
use Codemystify\TypesGenerator\Console\CleanupTypesCommand;
use Codemystify\TypesGenerator\Console\GenerateTypesCommand;
use Codemystify\TypesGenerator\Console\StatsCommand;
use Codemystify\TypesGenerator\Services\FileTypeDetector;
use Codemystify\TypesGenerator\Services\IntelligentTypeAggregator;
use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;
use Codemystify\TypesGenerator\Services\StructureParser;
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

        // Register core services
        $this->app->singleton(FileTypeDetector::class);
        $this->app->singleton(StructureParser::class);
        $this->app->singleton(TypeScriptGenerator::class);
        $this->app->singleton(IntelligentTypeAggregator::class);

        // Register main service with dependencies
        $this->app->singleton(SimpleTypeGeneratorService::class, function ($app) {
            return new SimpleTypeGeneratorService(
                $app->make(StructureParser::class),
                $app->make(TypeScriptGenerator::class),
                $app->make(FileTypeDetector::class)
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
                AnalyzeTypesCommand::class,
                CleanupTypesCommand::class,
                StatsCommand::class,
            ]);
        }
    }

    public function provides(): array
    {
        return [
            SimpleTypeGeneratorService::class,
            IntelligentTypeAggregator::class,
            FileTypeDetector::class,
            StructureParser::class,
            TypeScriptGenerator::class,
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
