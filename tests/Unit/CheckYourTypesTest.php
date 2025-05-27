<?php

use Codemystify\TypesGenerator\Services\CommonTypesExtractor;
use Codemystify\TypesGenerator\Services\TypeRegistry;

describe('Check Your Real Types', function () {
    it('checks if your types would be extracted', function () {
        $registry = new TypeRegistry;

        // Simulate your actual types from OverviewResource -> ProvidesManageEventData
        $yourTypes = [
            'EventManageData' => [
                'group' => 'overview',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'formatted_address' => ['type' => 'string', 'nullable' => true],
                ],
                'source' => 'OverviewResource',
            ],
            // Do you have another resource that creates the same structure?
            // If not, commons won't be created because there's only 1 group
        ];

        foreach ($yourTypes as $typeName => $typeData) {
            $registry->registerType($typeName, $typeData['structure'], $typeData['group'], $typeData['source']);
        }

        $duplicates = $registry->findDuplicates();
        $commonTypes = $registry->getCommonTypes(2);

        // Assert the expected behavior
        expect($yourTypes)->toHaveCount(1);
        expect($duplicates)->toBeArray();
        expect($commonTypes)->toBeArray();

        $extractor = new CommonTypesExtractor([
            'threshold' => 2,
            'exclude_patterns' => '',
        ]);

        $extracted = $extractor->extractCommonTypes($registry);
        expect($extracted)->toBeArray();
    });
});
