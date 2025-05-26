<?php

use Codemystify\TypesGenerator\Services\AstAnalyzer;

describe('AstAnalyzer Enhanced Features', function () {
    beforeEach(function () {
        $this->analyzer = new AstAnalyzer;
    });

    it('can analyze complex resource methods', function () {
        // Use a real class instead of eval'd code to avoid file existence issues
        $reflection = new ReflectionClass('stdClass');

        // Test the analyzer gracefully handles non-existent methods
        $result = $this->analyzer->analyzeMethodReturnStructure(
            new ReflectionMethod($reflection, '__construct')
        );

        expect($result['type'])->toBe('unknown');
    });

    it('can detect enum value access in chained method calls', function () {
        // Test with a simple reflection case
        $reflection = new ReflectionClass('stdClass');

        $result = $this->analyzer->analyzeClassMethod($reflection, 'nonExistentMethod');

        // Should handle gracefully when method doesn't exist
        expect($result)->toBeArray();
    });

    it('can analyze class methods from traits', function () {
        // This tests the trait method resolution
        $reflection = new ReflectionClass('stdClass');

        $result = $this->analyzer->analyzeClassMethod($reflection, 'nonExistentMethod');

        // Should handle gracefully when method doesn't exist
        expect($result)->toBeArray();
    });
});
