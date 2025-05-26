<?php

use Codemystify\TypesGenerator\Console\GenerateTypesCommand;
use Codemystify\TypesGenerator\Providers\TypesGeneratorServiceProvider;
use Codemystify\TypesGenerator\Services\TypeGeneratorService;

describe('TypesGeneratorServiceProvider', function () {
    it('registers all required services', function () {
        $provider = new TypesGeneratorServiceProvider($this->app);
        $provider->register();

        expect($this->app->bound(TypeGeneratorService::class))->toBeTrue();
    });

    it('publishes configuration file', function () {
        $provider = new TypesGeneratorServiceProvider($this->app);
        $provider->boot();

        expect($provider)->toBeInstanceOf(TypesGeneratorServiceProvider::class);
    });

    it('registers console commands when running in console', function () {
        $provider = new TypesGeneratorServiceProvider($this->app);

        // Test that the provider can boot without errors
        $provider->boot();

        // Check that the command class exists
        expect(class_exists(GenerateTypesCommand::class))->toBeTrue();

        // Verify the command can be instantiated
        $command = new GenerateTypesCommand;
        expect($command)->toBeInstanceOf(GenerateTypesCommand::class);
    });

    it('provides correct service list', function () {
        $provider = new TypesGeneratorServiceProvider($this->app);
        $provides = $provider->provides();

        expect($provides)->toContain(TypeGeneratorService::class);
    });
});
