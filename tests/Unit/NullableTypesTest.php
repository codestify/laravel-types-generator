<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\Facades\File;

describe('Nullable vs Optional Types', function () {
    beforeEach(function () {
        $this->tempPath = sys_get_temp_dir().'/types-generator-nullable-test-'.uniqid();
        File::makeDirectory($this->tempPath, 0755, true);

        config([
            'types-generator.output.path' => $this->tempPath,
            'types-generator.output.filename_pattern' => '{group}.ts',
            'types-generator.generation.strict_types' => true,
            'types-generator.generation.include_readonly' => true,
            'types-generator.commons.enabled' => false, // Disable for simpler testing
        ]);

        $this->generator = new TypeScriptGenerator;
    });

    afterEach(function () {
        if (File::exists($this->tempPath)) {
            File::deleteDirectory($this->tempPath);
        }
    });

    it('generates nullable types correctly', function () {
        $types = [
            'TestType' => [
                'group' => 'test',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'nullable_field' => ['type' => 'string', 'nullable' => true],
                    'optional_field' => ['type' => 'string', 'optional' => true],
                    'default_field' => ['type' => 'string', 'default' => 'test'],
                ],
                'source' => 'TestResource',
                'config' => (object) ['name' => 'TestType', 'group' => 'test'],
            ],
        ];

        $this->generator->generateFiles($types);

        expect(File::exists($this->tempPath.'/test.ts'))->toBeTrue();
        $content = File::get($this->tempPath.'/test.ts');

        // Required field - no ? and no | null
        expect($content)->toContain('readonly id: string;');

        // Nullable field - no ? but has | null
        expect($content)->toContain('readonly nullable_field: string | null;');

        // Optional field - has ? but no | null
        expect($content)->toContain('readonly optional_field?: string;');

        // Default field - has ? but no | null
        expect($content)->toContain('readonly default_field?: string;');
    });

    it('handles nullable arrays correctly', function () {
        $types = [
            'TestType' => [
                'group' => 'test',
                'structure' => [
                    'nullable_array' => ['type' => 'array', 'nullable' => true, 'items' => ['type' => 'string']],
                ],
                'source' => 'TestResource',
                'config' => (object) ['name' => 'TestType', 'group' => 'test'],
            ],
        ];

        $this->generator->generateFiles($types);

        $content = File::get($this->tempPath.'/test.ts');

        // Nullable array - no ? but has | null
        expect($content)->toContain('readonly nullable_array: string[] | null;');
    });

    it('handles nullable objects correctly', function () {
        $types = [
            'TestType' => [
                'group' => 'test',
                'structure' => [
                    'nullable_object' => [
                        'type' => 'object',
                        'nullable' => true,
                        'structure' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
                'source' => 'TestResource',
                'config' => (object) ['name' => 'TestType', 'group' => 'test'],
            ],
        ];

        $this->generator->generateFiles($types);

        $content = File::get($this->tempPath.'/test.ts');

        // Should contain the object type with | null
        expect($content)->toContain('| null');
        expect($content)->toContain('name: string');
    });

    it('handles strict_types false correctly', function () {
        config(['types-generator.generation.strict_types' => false]);

        $generator = new TypeScriptGenerator;

        $types = [
            'TestType' => [
                'group' => 'test',
                'structure' => [
                    'required_field' => ['type' => 'string'],
                    'nullable_field' => ['type' => 'string', 'nullable' => true],
                ],
                'source' => 'TestResource',
                'config' => (object) ['name' => 'TestType', 'group' => 'test'],
            ],
        ];

        $generator->generateFiles($types);

        $content = File::get($this->tempPath.'/test.ts');

        // When strict_types is false, all fields should be optional
        expect($content)->toContain('readonly required_field?: string;');
        expect($content)->toContain('readonly nullable_field?: string | null;');
    });
});
