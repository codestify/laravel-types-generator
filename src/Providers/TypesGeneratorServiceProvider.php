<?php

namespace Codemystify\TypesGenerator\Providers;

use Codemystify\TypesGenerator\Console\GenerateTypesCommand;
use Codemystify\TypesGenerator\Services\MigrationAnalyzer;
use Codemystify\TypesGenerator\Services\SimpleReflectionAnalyzer;
use Codemystify\TypesGenerator\Services\TypeGeneratorService;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\ServiceProvider;

class TypesGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/types-generator.php',
            'types-generator'
        );

        $this->app->singleton(TypeGeneratorService::class, function ($app) {
            return new TypeGeneratorService(
                $app->make(SimpleReflectionAnalyzer::class),
                $app->make(MigrationAnalyzer::class),
                $app->make(TypeScriptGenerator::class)
            );
        });

        $this->app->singleton(SimpleReflectionAnalyzer::class);
        $this->app->singleton(MigrationAnalyzer::class);
        $this->app->singleton(TypeScriptGenerator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/types-generator.php' => config_path('types-generator.php'),
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
}
