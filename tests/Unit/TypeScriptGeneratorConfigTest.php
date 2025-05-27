<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;

describe('TypeScriptGenerator Config Integration', function () {
    beforeEach(function () {
        $this->originalConfig = config('types-generator');
    });

    afterEach(function () {
        config(['types-generator' => $this->originalConfig]);
    });

    it('respects export_interfaces config for type aliases', function () {
        config(['types-generator.generation.export_interfaces' => false]);

        $generator = new TypeScriptGenerator;
        $structure = [
            'id' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $result = $generator->generateInterface('User', $structure);

        expect($result)->toContain('export type User =');
    });

    it('respects include_comments config', function () {
        config(['types-generator.generation.include_comments' => false]);

        $generator = new TypeScriptGenerator;
        $structure = [
            'metadata' => [
                'type' => 'object',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
                'frequency' => 3,
            ],
        ];

        $result = $generator->generateInterface('Entity', $structure);

        expect($result)->not()->toContain('// Used in 3 types');
    });

    it('respects include_readonly config', function () {
        config(['types-generator.generation.include_readonly' => true]);

        $generator = new TypeScriptGenerator;
        $structure = [
            'id' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
                'readonly' => true,
            ],
        ];

        $result = $generator->generateInterface('Entity', $structure);

        expect($result)->toContain('readonly id: string;');
    });

    it('respects strict_types config', function () {
        config(['types-generator.generation.strict_types' => true]);

        $generator = new TypeScriptGenerator;
        $structure = [
            'data' => [
                'type' => 'any',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $result = $generator->generateInterface('Entity', $structure);

        expect($result)->toContain('data: unknown;');
    });

    it('allows any type when strict_types is disabled', function () {
        config(['types-generator.generation.strict_types' => false]);

        $generator = new TypeScriptGenerator;
        $structure = [
            'data' => [
                'type' => 'any',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $result = $generator->generateInterface('Entity', $structure);

        expect($result)->toContain('data: any;');
    });
});
