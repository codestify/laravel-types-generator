<?php

describe('Basic Package Test', function () {
    it('package services can be instantiated', function () {
        expect(app(\Codemystify\TypesGenerator\Services\FileTypeDetector::class))
            ->toBeInstanceOf(\Codemystify\TypesGenerator\Services\FileTypeDetector::class);

        expect(app(\Codemystify\TypesGenerator\Services\StructureParser::class))
            ->toBeInstanceOf(\Codemystify\TypesGenerator\Services\StructureParser::class);

        expect(app(\Codemystify\TypesGenerator\Services\TypeScriptGenerator::class))
            ->toBeInstanceOf(\Codemystify\TypesGenerator\Services\TypeScriptGenerator::class);

        expect(app(\Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService::class))
            ->toBeInstanceOf(\Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService::class);
    });

    it('attribute works correctly', function () {
        $attribute = new \Codemystify\TypesGenerator\Attributes\GenerateTypes(
            name: 'TestType',
            structure: ['id' => 'string'],
        );

        expect($attribute->name)->toBe('TestType')
            ->and($attribute->structure)->toBe(['id' => 'string']);
    });
});
