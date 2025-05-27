<?php

use Codemystify\TypesGenerator\Attributes\GenerateTypes;

describe('GenerateTypes Attribute', function () {
    it('can be instantiated with required name parameter', function () {
        $attribute = new GenerateTypes(name: 'TestType');

        expect($attribute->name)->toBe('TestType')
            ->and($attribute->structure)->toBe([])
            ->and($attribute->types)->toBe([])
            ->and($attribute->group)->toBeNull()
            ->and($attribute->fileType)->toBeNull()
            ->and($attribute->export)->toBeTrue();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new GenerateTypes(
            name: 'ComplexType',
            structure: ['id' => 'string', 'name' => 'string'],
            types: ['User' => ['id' => 'string']],
            group: 'events',
            fileType: 'resource',
            export: false
        );

        expect($attribute->name)->toBe('ComplexType')
            ->and($attribute->structure)->toBe(['id' => 'string', 'name' => 'string'])
            ->and($attribute->types)->toBe(['User' => ['id' => 'string']])
            ->and($attribute->group)->toBe('events')
            ->and($attribute->fileType)->toBe('resource')
            ->and($attribute->export)->toBeFalse();
    });

    it('has correct attribute target configuration', function () {
        $reflection = new ReflectionClass(GenerateTypes::class);
        $attributes = $reflection->getAttributes();

        expect($attributes)->toHaveCount(1);

        $reflectionAttribute = $attributes[0];
        expect($reflectionAttribute->getName())->toBe(Attribute::class);
    });

    it('validates attribute properties', function () {
        $attribute = new GenerateTypes(name: 'ValidationType');

        expect($attribute->name)->toBeString()
            ->and($attribute->export)->toBeBool()
            ->and($attribute->structure)->toBeArray()
            ->and($attribute->types)->toBeArray();
    });
});
