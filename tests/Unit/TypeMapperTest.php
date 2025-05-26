<?php

use Codemystify\TypesGenerator\Utils\TypeMapper;

describe('TypeMapper Utility', function () {
    it('maps PHP types to TypeScript correctly', function () {
        expect(TypeMapper::mapPhpToTypeScript('int'))->toBe('number')
            ->and(TypeMapper::mapPhpToTypeScript('string'))->toBe('string')
            ->and(TypeMapper::mapPhpToTypeScript('bool'))->toBe('boolean')
            ->and(TypeMapper::mapPhpToTypeScript('array'))->toBe('any[]')
            ->and(TypeMapper::mapPhpToTypeScript('object'))->toBe('Record<string, any>')
            ->and(TypeMapper::mapPhpToTypeScript('unknown_type'))->toBe('any');
    });

    it('maps Laravel types to TypeScript correctly', function () {
        expect(TypeMapper::mapLaravelToTypeScript('bigIncrements'))->toBe('number')
            ->and(TypeMapper::mapLaravelToTypeScript('text'))->toBe('string')
            ->and(TypeMapper::mapLaravelToTypeScript('boolean'))->toBe('boolean')
            ->and(TypeMapper::mapLaravelToTypeScript('datetime'))->toBe('string')
            ->and(TypeMapper::mapLaravelToTypeScript('json'))->toBe('Record<string, any>')
            ->and(TypeMapper::mapLaravelToTypeScript('uuid'))->toBe('string');
    });

    it('infers types from values correctly', function () {
        expect(TypeMapper::inferTypeFromValue(null))->toBe(['type' => 'null', 'nullable' => true])
            ->and(TypeMapper::inferTypeFromValue(true))->toBe(['type' => 'boolean'])
            ->and(TypeMapper::inferTypeFromValue(42))->toBe(['type' => 'number'])
            ->and(TypeMapper::inferTypeFromValue(3.14))->toBe(['type' => 'number'])
            ->and(TypeMapper::inferTypeFromValue('hello'))->toBe(['type' => 'string'])
            ->and(TypeMapper::inferTypeFromValue((object) ['key' => 'value']))->toBe(['type' => 'object']);
    });

    it('handles array type inference', function () {
        $emptyArray = [];
        $result = TypeMapper::inferTypeFromValue($emptyArray);
        expect($result['type'])->toBe('array')
            ->and($result['items']['type'])->toBe('any');

        $stringArray = ['a', 'b', 'c'];
        $result = TypeMapper::inferTypeFromValue($stringArray);
        expect($result['type'])->toBe('array')
            ->and($result['items']['type'])->toBe('string');

        $assocArray = ['key' => 'value', 'another' => 'item'];
        $result = TypeMapper::inferTypeFromValue($assocArray);
        expect($result['type'])->toBe('object');
    });

    it('handles complex nested structures', function () {
        $complex = [
            'user' => ['id' => 1, 'name' => 'John'],
            'items' => [1, 2, 3],
            'active' => true,
        ];

        $result = TypeMapper::inferTypeFromValue($complex);
        expect($result['type'])->toBe('object');
    });
});
