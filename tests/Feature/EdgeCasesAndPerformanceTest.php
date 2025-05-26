<?php

use Codemystify\TypesGenerator\Exceptions\AnalysisException;
use Codemystify\TypesGenerator\Exceptions\GenerationException;
use Codemystify\TypesGenerator\Exceptions\TypesGeneratorException;
use Codemystify\TypesGenerator\Utils\TypeMapper;

describe('Exception Handling', function () {
    it('creates appropriate exception types', function () {
        $configException = TypesGeneratorException::invalidConfiguration('test', 'message');
        expect($configException)->toBeInstanceOf(TypesGeneratorException::class)
            ->and($configException->getMessage())->toContain('Invalid configuration for \'test\'');

        $directoryException = TypesGeneratorException::directoryNotFound('/invalid/path');
        expect($directoryException->getMessage())->toContain('Directory not found');

        $pathException = TypesGeneratorException::invalidPath('/unsafe/../path');
        expect($pathException->getMessage())->toContain('Invalid or unsafe path');
    });

    it('creates analysis exceptions', function () {
        $analysisException = AnalysisException::methodAnalysisFailed('TestClass', 'testMethod', 'reason');
        expect($analysisException)->toBeInstanceOf(AnalysisException::class)
            ->and($analysisException->getMessage())->toContain('Failed to analyze method TestClass::testMethod');

        $structureException = AnalysisException::invalidMethodStructure('TestClass', 'testMethod');
        expect($structureException->getMessage())->toContain('Invalid method structure');

        $unsupportedException = AnalysisException::unsupportedMethodType('UnsupportedClass');
        expect($unsupportedException->getMessage())->toContain('Unsupported method type');
    });

    it('creates generation exceptions', function () {
        $fileException = GenerationException::fileWriteFailed('/path/to/file', 'permission denied');
        expect($fileException)->toBeInstanceOf(GenerationException::class)
            ->and($fileException->getMessage())->toContain('Failed to write file');

        $typeException = GenerationException::invalidTypeStructure('InvalidType');
        expect($typeException->getMessage())->toContain('Invalid type structure');
    });
});

describe('Performance Edge Cases', function () {
    it('handles large data structures efficiently', function () {
        $largeArray = array_fill(0, 1000, ['id' => 1, 'name' => 'test']);

        $startTime = microtime(true);
        $result = TypeMapper::inferTypeFromValue($largeArray);
        $endTime = microtime(true);

        expect($result['type'])->toBe('array')
            ->and($endTime - $startTime)->toBeLessThan(1.0);
        // Should complete in under 1 second
    });

    it('handles deeply nested structures', function () {
        $deeplyNested = ['level1' => ['level2' => ['level3' => ['level4' => 'value']]]];

        $result = TypeMapper::inferTypeFromValue($deeplyNested);
        expect($result['type'])->toBe('object');
    });

    it('handles memory efficiently with large datasets', function () {
        $memoryBefore = memory_get_usage();

        // Create large dataset
        $largeDataset = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeDataset[] = createMockEvent(['id' => $i]);
        }

        $memoryAfter = memory_get_usage();
        $memoryDiff = $memoryAfter - $memoryBefore;

        // Should not use more than 50MB for this dataset
        expect($memoryDiff)->toBeLessThan(50 * 1024 * 1024);
    });
});
