<?php

use Codemystify\TypesGenerator\Attributes\GenerateTypes;

describe('GenerateTypes Attribute', function () {
    it('can be instantiated with required name parameter', function () {
        $attribute = new GenerateTypes(name: 'TestType');

        expect($attribute->name)->toBe('TestType')
            ->and($attribute->export)->toBeTrue()
            ->and($attribute->group)->toBeNull()
            ->and($attribute->options)->toBe([])
            ->and($attribute->recursive)->toBeTrue()
            ->and($attribute->description)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new GenerateTypes(
            name: 'ComplexType',
            export: false,
            group: 'events',
            options: ['include_relations' => true],
            recursive: false,
            description: 'A complex type definition'
        );

        expect($attribute->name)->toBe('ComplexType')
            ->and($attribute->export)->toBeFalse()
            ->and($attribute->group)->toBe('events')
            ->and($attribute->options)->toBe(['include_relations' => true])
            ->and($attribute->recursive)->toBeFalse()
            ->and($attribute->description)->toBe('A complex type definition');
    });

    it('has correct attribute target configuration', function () {
        $reflection = new ReflectionClass(GenerateTypes::class);
        $attributes = $reflection->getAttributes();

        expect($attributes)->toHaveCount(1);
    });

    it('can validate attribute properties', function () {
        $attribute = new GenerateTypes(name: 'ValidationType');

        expect($attribute->name)->toBeString()
            ->and($attribute->export)->toBeBool()
            ->and($attribute->recursive)->toBeBool();
    });
});
