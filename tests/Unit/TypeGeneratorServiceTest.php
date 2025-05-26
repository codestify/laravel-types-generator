<?php

use Codemystify\TypesGenerator\Services\MigrationAnalyzer;
use Codemystify\TypesGenerator\Services\SimpleReflectionAnalyzer;
use Codemystify\TypesGenerator\Services\TypeGeneratorService;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;

describe('TypeGeneratorService', function () {

    it('can be instantiated with dependencies', function () {
        $reflectionAnalyzer = Mockery::mock(SimpleReflectionAnalyzer::class);
        $migrationAnalyzer = Mockery::mock(MigrationAnalyzer::class);
        $typeScriptGenerator = Mockery::mock(TypeScriptGenerator::class);

        $service = new TypeGeneratorService(
            $reflectionAnalyzer,
            $migrationAnalyzer,
            $typeScriptGenerator
        );

        expect($service)->toBeInstanceOf(TypeGeneratorService::class);
    });

    it('can generate types with empty options', function () {
        $migrationAnalyzer = Mockery::mock(MigrationAnalyzer::class);
        $migrationAnalyzer->shouldReceive('analyzeAllMigrations')->andReturn([]);

        $reflectionAnalyzer = Mockery::mock(SimpleReflectionAnalyzer::class);

        $typeScriptGenerator = Mockery::mock(TypeScriptGenerator::class);
        $typeScriptGenerator->shouldReceive('generateFiles')->with([])->andReturn([]);

        $service = new TypeGeneratorService(
            $reflectionAnalyzer,
            $migrationAnalyzer,
            $typeScriptGenerator
        );

        $result = $service->generateTypes([]);

        expect($result)->toBeArray();
    });

    it('handles missing directories gracefully', function () {
        $migrationAnalyzer = Mockery::mock(MigrationAnalyzer::class);
        $migrationAnalyzer->shouldReceive('analyzeAllMigrations')->andReturn([]);

        $reflectionAnalyzer = Mockery::mock(SimpleReflectionAnalyzer::class);

        $typeScriptGenerator = Mockery::mock(TypeScriptGenerator::class);
        $typeScriptGenerator->shouldReceive('generateFiles')->andReturn([]);

        $service = new TypeGeneratorService(
            $reflectionAnalyzer,
            $migrationAnalyzer,
            $typeScriptGenerator
        );

        expect($service->generateTypes())->toBeArray();
    });
});
