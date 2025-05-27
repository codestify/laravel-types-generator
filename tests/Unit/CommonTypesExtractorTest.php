<?php

use Codemystify\TypesGenerator\Services\CommonTypesExtractor;
use Codemystify\TypesGenerator\Services\TypeRegistry;

describe('CommonTypesExtractor', function () {
    beforeEach(function () {
        $this->registry = new TypeRegistry;
        $this->extractor = new CommonTypesExtractor([
            'threshold' => 2,
            'file_name' => 'common',
            'exclude_patterns' => '',
        ]);
    });

    it('extracts common types above threshold', function () {
        $structure = [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ];

        $this->registry->registerType('User', $structure, 'users', 'UserResource');
        $this->registry->registerType('UserData', $structure, 'events', 'EventResource');
        $this->registry->registerType('UserInfo', $structure, 'organizations', 'OrgResource');

        $extracted = $this->extractor->extractCommonTypes($this->registry);

        expect($extracted)->toHaveCount(1);
        $extractedType = array_values($extracted)[0];
        expect($extractedType['usageCount'])->toBe(3);
        expect($extractedType['groups'])->toContain('users', 'events', 'organizations');
    });

    it('excludes simple types from extraction', function () {
        $simpleStructure = ['id' => ['type' => 'string']]; // Only 1 field

        $this->registry->registerType('SimpleType1', $simpleStructure, 'group1', 'source1');
        $this->registry->registerType('SimpleType2', $simpleStructure, 'group2', 'source2');

        $extracted = $this->extractor->extractCommonTypes($this->registry);

        expect($extracted)->toBeEmpty();
    });

    it('selects preferred type names correctly', function () {
        $structure = [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ];

        // Register types with different quality names
        $this->registry->registerType('UserResponse', $structure, 'users', 'source1');
        $this->registry->registerType('User', $structure, 'events', 'source2');
        $this->registry->registerType('UserData', $structure, 'organizations', 'source3');

        $extracted = $this->extractor->extractCommonTypes($this->registry);

        expect($extracted)->toHaveCount(1);
        $extractedType = array_values($extracted)[0];
        expect($extractedType['name'])->toBe('User'); // Should prefer 'User' over 'UserResponse' or 'UserData'
    });

    it('respects exclude patterns', function () {
        $config = [
            'threshold' => 2,
            'exclude_patterns' => 'Test,Mock',
        ];
        $extractor = new CommonTypesExtractor($config);

        $structure = [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'data' => ['type' => 'string'],
        ];

        $this->registry->registerType('TestUser', $structure, 'group1', 'source1');
        $this->registry->registerType('MockUser', $structure, 'group2', 'source2');

        $extracted = $extractor->extractCommonTypes($this->registry);

        expect($extracted)->toBeEmpty();
    });

    it('returns correct commons file name', function () {
        expect($this->extractor->determineCommonsFileName())->toBe('common');

        $customExtractor = new CommonTypesExtractor(['file_name' => 'shared']);
        expect($customExtractor->determineCommonsFileName())->toBe('shared');
    });
});
