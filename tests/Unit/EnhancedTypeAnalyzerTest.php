<?php

use Codemystify\TypesGenerator\Services\EnhancedTypeAnalyzer;
use Codemystify\TypesGenerator\Services\MigrationAnalyzer;

describe('EnhancedTypeAnalyzer', function () {
    beforeEach(function () {
        $migrationAnalyzer = new MigrationAnalyzer;
        $this->analyzer = new EnhancedTypeAnalyzer($migrationAnalyzer);
    });

    it('can analyze enum value access', function () {
        // Create mock resource class
        $mockCode = '<?php
        class TestEventResource {
            // Mock resource for testing
        }';

        eval(str_replace('<?php', '', $mockCode));

        $reflection = new ReflectionClass('TestEventResource');
        $result = $this->analyzer->analyzeEnumValueAccess('status', $reflection);

        expect($result['type'])->toBe('string');
        expect($result['description'])->toBe('Enum value');
    });

    it('can analyze protected method patterns', function () {
        $reflection = new ReflectionClass('stdClass'); // Use stdClass as mock

        $result = $this->analyzer->analyzeProtectedMethod('calculateStats', $reflection);

        expect($result['type'])->toBe('object');
        expect($result['structure'])->toHaveKey('soldTickets');
        expect($result['structure'])->toHaveKey('totalRevenue');
        expect($result['structure']['soldTickets']['type'])->toBe('number');
    });

    it('can infer property types for common Laravel patterns', function () {
        $reflection = new ReflectionClass('stdClass');

        $tests = [
            'id' => 'number',
            'ulid' => 'string',
            'title' => 'string',
            'start_date' => 'string',
            'is_active' => 'boolean',
            'price' => 'number',
        ];

        foreach ($tests as $property => $expectedType) {
            $result = $this->analyzer->inferPropertyType($property, $reflection);
            expect($result['type'])->toBe($expectedType);
        }
    });

    it('handles unknown properties gracefully', function () {
        $reflection = new ReflectionClass('stdClass');

        $result = $this->analyzer->inferPropertyType('unknown_field', $reflection);

        expect($result['type'])->toBe('string');
        expect($result['nullable'])->toBe(true);
    });
});
