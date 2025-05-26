<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;

describe('TypeScript Generation Edge Cases', function () {
    it('handles circular references gracefully', function () {
        $types = [
            'UserWithPosts' => [
                'config' => (object) ['name' => 'UserWithPosts', 'group' => 'test'],
                'structure' => [
                    'id' => ['type' => 'number'],
                    'posts' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'structure' => [
                                'id' => ['type' => 'number'],
                                'user_id' => ['type' => 'number'],
                                // This could create circular reference
                                'user' => ['type' => 'reference', 'reference' => 'UserWithPosts'],
                            ],
                        ],
                    ],
                ],
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;

        // Should not throw an exception
        $result = $generator->generateFiles($types);
        expect($result)->toBeArray();
    });

    it('handles special characters in property names', function () {
        $types = [
            'SpecialProps' => [
                'config' => (object) ['name' => 'SpecialProps', 'group' => 'test'],
                'structure' => [
                    'normal_prop' => ['type' => 'string'],
                    'prop-with-dashes' => ['type' => 'string'],
                    'prop_with_numbers123' => ['type' => 'number'],
                    '$special_char' => ['type' => 'boolean'],
                ],
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $results = $generator->generateFiles($types);

        expect($results)->toHaveCount(1);
    });

    it('generates valid TypeScript for empty structures', function () {
        $types = [
            'EmptyType' => [
                'config' => (object) ['name' => 'EmptyType', 'group' => 'test'],
                'structure' => [],
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $results = $generator->generateFiles($types);

        expect($results)->toHaveCount(1);
    });

    it('handles union types and optional properties', function () {
        $types = [
            'UnionType' => [
                'config' => (object) ['name' => 'UnionType', 'group' => 'test'],
                'structure' => [
                    'id' => ['type' => 'number'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'pending']],
                    'optional_field' => ['type' => 'string', 'nullable' => true],
                    'default_field' => ['type' => 'boolean', 'default' => true],
                ],
                'source' => 'test',
                'group' => 'test',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $results = $generator->generateFiles($types);

        expect($results)->toHaveCount(1);
    });
});
