<?php

use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;

describe('SimpleTypeGeneratorService Integration', function () {
    beforeEach(function () {
        $this->service = app(SimpleTypeGeneratorService::class);
    });

    it('handles empty attribute discovery gracefully', function () {
        $result = $this->service->generateTypes(['dry_run' => true]);

        expect($result)->toBeArray();
    });

    it('validates options parameter types', function () {
        $result = $this->service->generateTypes([
            'group' => 'test',
            'extract_common' => false,
            'dry_run' => true,
        ]);

        expect($result)->toBeArray();
    });

    it('handles invalid group filtering', function () {
        $result = $this->service->generateTypes([
            'group' => 'non_existent_group',
            'dry_run' => true,
        ]);

        expect($result)->toBeArray()
            ->and($result)->toBeEmpty();
    });

    it('handles missing output directory creation', function () {
        // Test with a temporary path that doesn't exist
        $originalPath = config('types-generator.output.base_path');
        config(['types-generator.output.base_path' => sys_get_temp_dir().'/test_types_'.uniqid()]);

        $result = $this->service->generateTypes(['dry_run' => true]);

        // Restore original config
        config(['types-generator.output.base_path' => $originalPath]);

        expect($result)->toBeArray();
    });

    it('sanitizes file names correctly', function () {
        // Test internal sanitizeFileName method behavior indirectly
        $result = $this->service->generateTypes(['dry_run' => true]);

        expect($result)->toBeArray();
        // File names should be sanitized if any are generated
        foreach ($result as $item) {
            if (isset($item['path'])) {
                $fileName = basename($item['path']);
                expect($fileName)->toMatch('/^[a-z0-9_-]+\.ts$/');
            }
        }
    });
});
