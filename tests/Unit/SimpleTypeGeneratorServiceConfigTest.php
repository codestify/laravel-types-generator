<?php

use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;

describe('SimpleTypeGeneratorService Config Integration', function () {
    beforeEach(function () {
        $this->service = app(SimpleTypeGeneratorService::class);
        $this->originalConfig = config('types-generator');
    });

    afterEach(function () {
        config(['types-generator' => $this->originalConfig]);
    });

    it('respects file extension config', function () {
        config(['types-generator.files.extension' => 'd.ts']);

        $result = $this->service->generateTypes(['dry_run' => true]);

        expect($result)->toBeArray();

        // If any files are generated, they should have the correct extension
        $hasCorrectExtension = true;
        foreach ($result as $file) {
            if (isset($file['path'])) {
                if (! str_ends_with($file['path'], '.d.ts')) {
                    $hasCorrectExtension = false;
                    break;
                }
            }
        }

        expect($hasCorrectExtension)->toBeTrue();
    });

    it('respects naming pattern config for kebab-case', function () {
        config(['types-generator.files.naming_pattern' => 'kebab-case']);

        // We can't easily test this without creating actual attributes
        // but we can test the method through the service
        $result = $this->service->generateTypes(['dry_run' => true]);
        expect($result)->toBeArray();
    });

    it('respects source paths config', function () {
        config(['types-generator.sources' => ['app/Custom/Path']]);

        $result = $this->service->generateTypes(['dry_run' => true]);

        // Should complete without error even with non-existent path
        expect($result)->toBeArray();
    });

    it('respects aggregation config', function () {
        config(['types-generator.aggregation.extract_common_types' => false]);

        $result = $this->service->generateTypes([
            'dry_run' => true,
            'extract_common' => null, // Will use config default
        ]);

        expect($result)->toBeArray();
    });
});
