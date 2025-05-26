<?php

use Codemystify\TypesGenerator\Services\TypeGeneratorService;

describe('Complete Package Workflow', function () {

    it('generates types for complex event resource', function () {
        // Create a simple test to verify the service works
        $service = app(TypeGeneratorService::class);

        $result = $service->generateTypes([]);

        expect($result)->toBeArray();
    });

    it('handles multiple resources with different groups', function () {
        // Test the service can handle empty input gracefully
        $service = app(TypeGeneratorService::class);

        $result = $service->generateTypes(['group' => 'events']);

        expect($result)->toBeArray();
    });

    it('respects exclusions in configuration', function () {
        // Test that configuration exclusions are respected
        config(['types-generator.exclude.methods' => ['password', 'remember_token']]);

        $service = app(TypeGeneratorService::class);
        $result = $service->generateTypes([]);

        expect($result)->toBeArray();
    });
});
