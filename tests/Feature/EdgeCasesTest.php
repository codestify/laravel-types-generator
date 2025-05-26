<?php

use Codemystify\TypesGenerator\Services\MigrationAnalyzer;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;

describe('Edge Cases and Error Handling', function () {
    it('handles missing migration directory gracefully', function () {
        config(['types-generator.sources.migrations_path' => '/non/existent/path']);

        $analyzer = new MigrationAnalyzer;
        $result = $analyzer->analyzeAllMigrations();

        expect($result)->toBe([]);
    });

    it('handles invalid migration files gracefully', function () {
        // Test with corrupted migration content
        $analyzer = new MigrationAnalyzer;
        $result = $analyzer->analyzeAllMigrations();

        expect($result)->toBeArray();
    });

    it('handles TypeScript generation with invalid structure', function () {
        $types = [
            'InvalidType' => [
                'config' => (object) ['name' => 'InvalidType', 'group' => 'test'],
                'structure' => null, // Invalid structure
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;

        // Should not throw an exception
        $result = $generator->generateFiles($types);
        expect($result)->toBeArray();
    });

    it('handles empty type definitions', function () {
        $types = [];

        $generator = new TypeScriptGenerator;
        $result = $generator->generateFiles($types);

        expect($result)->toBe([]);
    });

    it('handles deeply nested object structures', function () {
        $types = [
            'DeepType' => [
                'config' => (object) ['name' => 'DeepType', 'group' => 'test'],
                'structure' => [
                    'level1' => [
                        'type' => 'object',
                        'structure' => [
                            'level2' => [
                                'type' => 'object',
                                'structure' => [
                                    'level3' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $result = $generator->generateFiles($types);

        expect($result)->toHaveCount(1);
    });
});
