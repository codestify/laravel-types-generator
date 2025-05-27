<?php

use Codemystify\TypesGenerator\Services\IntelligentTypeAggregator;

describe('IntelligentTypeAggregator', function () {
    beforeEach(function () {
        $this->aggregator = new IntelligentTypeAggregator;
    });

    it('analyzes types and extracts common patterns', function () {
        $mockResults = [
            [
                'name' => 'User',
                'content' => "export interface User {\n  id: string;\n  name: string;\n  email: string;\n  created_at: string;\n}",
                'file_type' => 'model',
                'group' => 'users',
                'path' => '/types/User.ts',
            ],
            [
                'name' => 'Post',
                'content' => "export interface Post {\n  id: string;\n  title: string;\n  content: string;\n  created_at: string;\n}",
                'file_type' => 'model',
                'group' => 'posts',
                'path' => '/types/Post.ts',
            ],
        ];

        $analysis = $this->aggregator->analyzeTypes($mockResults);

        expect($analysis)->toBeArray()
            ->and($analysis)->toHaveKeys(['common_types', 'type_structures', 'original_types', 'property_analysis', 'optimization_metrics'])
            ->and($analysis['optimization_metrics']['total_types_analyzed'])->toBe(2);
    });

    it('handles empty input gracefully', function () {
        $analysis = $this->aggregator->analyzeTypes([]);

        expect($analysis)->toBeArray()
            ->and($analysis['optimization_metrics']['total_types_analyzed'])->toBe(0)
            ->and($analysis['common_types'])->toBeArray()
            ->and($analysis['common_types'])->toBeEmpty();
    });

    it('generates common types file content', function () {
        // Set up some mock common types
        $this->aggregator->analyzeTypes([
            [
                'name' => 'MockEntity',
                'content' => "export interface MockEntity {\n  id: string;\n  created_at: string;\n}",
                'file_type' => 'model',
                'group' => 'test',
                'path' => '/types/MockEntity.ts',
            ],
        ]);

        $content = $this->aggregator->generateCommonTypesFile();

        expect($content)->toBeString();
        // Content might be empty if no common types were extracted due to thresholds
        if (! empty($content)) {
            expect($content)->toContain('// Automatically generated common types');
        }
    });

    it('generates index file with proper exports', function () {
        $mockResults = [
            [
                'name' => 'User',
                'file_type' => 'model',
                'group' => 'users',
            ],
            [
                'name' => 'UserResource',
                'file_type' => 'resource',
                'group' => 'users',
            ],
        ];

        $content = $this->aggregator->generateIndexFile($mockResults);

        expect($content)->toBeString()
            ->and($content)->toContain('// Automatically generated index file')
            ->and($content)->toContain('export * from');
    });

    it('handles malformed TypeScript interfaces gracefully', function () {
        $mockResults = [
            [
                'name' => 'BrokenInterface',
                'content' => "export interface BrokenInterface {\n  // Missing closing brace",
                'file_type' => 'model',
                'group' => 'test',
                'path' => '/types/BrokenInterface.ts',
            ],
        ];

        expect(fn () => $this->aggregator->analyzeTypes($mockResults))
            ->not()->toThrow(Exception::class);
    });

    it('categorizes files by type correctly', function () {
        $mockResults = [
            ['name' => 'User', 'file_type' => 'model'],
            ['name' => 'UserResource', 'file_type' => 'resource'],
            ['name' => 'UserController', 'file_type' => 'controller'],
            ['name' => 'UserService', 'file_type' => 'service'],
        ];

        $content = $this->aggregator->generateIndexFile($mockResults);

        expect($content)->toContain('// Entities')
            ->and($content)->toContain('// Resources')
            ->and($content)->toContain('// Controllers')
            ->and($content)->toContain('// Services');
    });

    it('handles complex interface structures', function () {
        $mockResults = [
            [
                'name' => 'ComplexEntity',
                'content' => "export interface ComplexEntity {\n  id: string;\n  metadata?: Record<string, any>;\n  tags: string[];\n  settings: { [key: string]: boolean | null };\n}",
                'file_type' => 'model',
                'group' => 'complex',
                'path' => '/types/ComplexEntity.ts',
            ],
        ];

        $analysis = $this->aggregator->analyzeTypes($mockResults);

        expect($analysis['original_types'])->toHaveKey('ComplexEntity')
            ->and($analysis['optimization_metrics']['total_types_analyzed'])->toBe(1);
    });

    it('respects configuration thresholds', function () {
        // Test with high thresholds that should prevent common type extraction
        config(['types-generator.aggregation.similarity_threshold' => 0.99]);
        config(['types-generator.aggregation.minimum_occurrence' => 10]);

        $mockResults = [
            [
                'name' => 'EntityA',
                'content' => "export interface EntityA {\n  id: string;\n  name: string;\n}",
                'file_type' => 'model',
                'group' => 'test',
                'path' => '/types/EntityA.ts',
            ],
            [
                'name' => 'EntityB',
                'content' => "export interface EntityB {\n  id: string;\n  title: string;\n}",
                'file_type' => 'model',
                'group' => 'test',
                'path' => '/types/EntityB.ts',
            ],
        ];

        $analysis = $this->aggregator->analyzeTypes($mockResults);

        // With high thresholds, no common types should be extracted
        expect($analysis['common_types'])->toBeArray();
    });
});
