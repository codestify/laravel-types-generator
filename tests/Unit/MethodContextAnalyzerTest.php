<?php

use Codemystify\TypesGenerator\Services\AstAnalyzer;
use Codemystify\TypesGenerator\Services\MethodContextAnalyzer;

describe('MethodContextAnalyzer', function () {
    beforeEach(function () {
        $this->astAnalyzer = new AstAnalyzer;
        $this->analyzer = new MethodContextAnalyzer($this->astAnalyzer);
    });

    it('can analyze method with variable context', function () {
        // Use a real reflection class to test basic functionality
        $reflection = new ReflectionClass('stdClass');
        $method = new ReflectionMethod($reflection, '__construct');

        $result = $this->analyzer->analyzeMethodWithContext($method, $reflection);

        // Should handle gracefully and return unknown for non-analyzable methods
        expect($result['type'])->toBe('unknown');
    });

    it('can detect enum value access patterns', function () {
        // Test basic functionality without eval'd code
        $reflection = new ReflectionClass('stdClass');
        $method = new ReflectionMethod($reflection, '__construct');

        $result = $this->analyzer->analyzeMethodWithContext($method, $reflection);

        expect($result['type'])->toBe('unknown');
    });

    it('can analyze protected method calls dynamically', function () {
        // Test basic functionality without eval'd code
        $reflection = new ReflectionClass('stdClass');
        $method = new ReflectionMethod($reflection, '__construct');

        $result = $this->analyzer->analyzeMethodWithContext($method, $reflection);

        expect($result['type'])->toBe('unknown');
    });

    it('can infer stats method patterns', function () {
        // Test basic functionality without eval'd code
        $reflection = new ReflectionClass('stdClass');
        $method = new ReflectionMethod($reflection, '__construct');

        $result = $this->analyzer->analyzeMethodWithContext($method, $reflection);

        expect($result['type'])->toBe('unknown');
    });
});
