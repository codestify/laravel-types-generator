<?php

use Codemystify\TypesGenerator\Services\StructureParser;

describe('StructureParser', function () {
    beforeEach(function () {
        $this->parser = new StructureParser;
    });

    it('parses simple string types', function () {
        $structure = ['name' => 'string', 'age' => 'number'];
        $result = $this->parser->parse($structure, []);

        expect($result['name'])->toBe([
            'type' => 'string',
            'isArray' => false,
            'optional' => false,
            'nullable' => false,
        ])
            ->and($result['age'])->toBe([
                'type' => 'number',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ]);
    });

    it('parses array types', function () {
        $structure = ['tags' => 'string[]', 'items' => 'CustomType[]'];
        $result = $this->parser->parse($structure, ['CustomType' => ['id' => 'string']]);

        expect($result['tags'])->toBe([
            'type' => 'string',
            'isArray' => true,
            'optional' => false,
            'nullable' => false,
        ])
            ->and($result['items'])->toBe([
                'type' => 'CustomType',
                'isArray' => true,
                'optional' => false,
                'nullable' => false,
            ]);
    });

    it('parses complex type definitions', function () {
        $structure = [
            'id' => ['type' => 'string', 'optional' => false],
            'name' => ['type' => 'string', 'nullable' => true],
            'avatar' => ['type' => 'string', 'optional' => true],
        ];

        $result = $this->parser->parse($structure, []);

        expect($result['id'])->toBe([
            'type' => 'string',
            'isArray' => false,
            'optional' => false,
            'nullable' => false,
        ])
            ->and($result['name'])->toBe([
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => true,
            ])
            ->and($result['avatar'])->toBe([
                'type' => 'string',
                'isArray' => false,
                'optional' => true,
                'nullable' => false,
            ]);
    });

    it('resolves type references', function () {
        $structure = ['user' => 'User', 'comments' => 'Comment[]'];
        $typeRegistry = [
            'User' => ['id' => 'string', 'name' => 'string'],
            'Comment' => ['id' => 'string', 'content' => 'string'],
        ];

        $result = $this->parser->parse($structure, $typeRegistry);

        expect($result['user']['type'])->toBe('User')
            ->and($result['comments']['type'])->toBe('Comment')
            ->and($result['comments']['isArray'])->toBeTrue();
    });

    it('handles nullable union types', function () {
        $structure = ['email' => 'string|null'];
        $result = $this->parser->parse($structure, []);

        expect($result['email'])->toBe([
            'type' => 'string',
            'isArray' => false,
            'optional' => false,
            'nullable' => true,
        ]);
    });

    it('handles edge cases and invalid input', function () {
        $structure = [
            'empty_string' => '',
            'null_value' => null,
            'empty_array' => '[]',
            'boolean_value' => true,
            'numeric_value' => 123,
            'whitespace_type' => '   string   ',
            'complex_union' => 'string|number|boolean',
        ];

        $result = $this->parser->parse($structure, []);

        expect($result['empty_string']['type'])->toBe('unknown')
            ->and($result['empty_string']['nullable'])->toBe(true)
            ->and($result['null_value']['type'])->toBe('unknown')
            ->and($result['null_value']['nullable'])->toBe(true)
            ->and($result['boolean_value']['type'])->toBe('boolean')
            ->and($result['numeric_value']['type'])->toBe('number')
            ->and($result['whitespace_type']['type'])->toBe('string')
            ->and($result['complex_union']['type'])->toBe('string|number|boolean');
    });

    it('validates array type definitions', function () {
        $structure = [
            'valid_array' => 'string[]',
            'empty_array_type' => '[]',
        ];

        $result = $this->parser->parse($structure, []);

        expect($result['valid_array']['type'])->toBe('string')
            ->and($result['valid_array']['isArray'])->toBe(true);

        // Empty array type should be skipped due to invalid definition
        expect($result)->not()->toHaveKey('empty_array_type');
    });

    it('skips invalid structure keys', function () {
        $structure = [
            '' => 'string',
            '   ' => 'number',
            'valid_key' => 'boolean',
        ];

        $result = $this->parser->parse($structure, []);

        expect($result)->toHaveKey('valid_key')
            ->and($result)->not()->toHaveKey('')
            ->and(count($result))->toBe(1);
    });

    it('handles empty structure gracefully', function () {
        $result = $this->parser->parse([], []);

        expect($result)->toBe([]);
    });
});
