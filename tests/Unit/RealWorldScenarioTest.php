<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\Facades\File;

describe('Real-world nullable scenario', function () {
    beforeEach(function () {
        $this->tempPath = sys_get_temp_dir().'/types-generator-realworld-test-'.uniqid();
        File::makeDirectory($this->tempPath, 0755, true);

        config([
            'types-generator.output.path' => $this->tempPath,
            'types-generator.output.filename_pattern' => '{group}.ts',
            'types-generator.generation.strict_types' => true,
            'types-generator.generation.include_readonly' => true,
            'types-generator.commons.enabled' => false,
        ]);

        $this->generator = new TypeScriptGenerator;
    });

    afterEach(function () {
        if (File::exists($this->tempPath)) {
            File::deleteDirectory($this->tempPath);
        }
    });

    it('generates correct type for formatted_address from ProvidesManageEventData', function () {
        // Simulating the actual structure from ProvidesManageEventData trait
        $types = [
            'OverviewPageTypes' => [
                'group' => 'overview',
                'structure' => [
                    'overviewEventData' => [
                        'type' => 'object',
                        'structure' => [
                            'id' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'formatted_address' => ['type' => 'string', 'nullable' => true], // This was the issue
                            'status' => ['type' => 'string'],
                        ],
                    ],
                ],
                'source' => 'OverviewResource',
                'config' => (object) ['name' => 'OverviewPageTypes', 'group' => 'overview'],
            ],
        ];

        $this->generator->generateFiles($types);

        $content = File::get($this->tempPath.'/overview.ts');

        // Should generate: formatted_address: string | null; (not formatted_address?: string;)
        expect($content)->toContain('formatted_address: string | null;');
        expect($content)->not->toContain('formatted_address?: string;');
    });
});
