<?php

use Codemystify\TypesGenerator\Services\CommonTypesExtractor;
use Codemystify\TypesGenerator\Services\TypeReferenceRewriter;
use Codemystify\TypesGenerator\Services\TypeRegistry;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\Facades\File;

describe('Debug Common Types Generation', function () {
    beforeEach(function () {
        $this->tempPath = sys_get_temp_dir().'/types-generator-debug-'.uniqid();
        File::makeDirectory($this->tempPath, 0755, true);

        config([
            'types-generator.output.path' => $this->tempPath,
            'types-generator.output.filename_pattern' => '{group}.ts',
            'types-generator.output.index_file' => true,
            'types-generator.commons.enabled' => true,
            'types-generator.commons.file_name' => 'common',
            'types-generator.commons.threshold' => 2,
            'types-generator.commons.include_in_index' => true,
        ]);

        $this->generator = new TypeScriptGenerator(
            new TypeRegistry,
            new CommonTypesExtractor(config('types-generator.commons')),
            new TypeReferenceRewriter(config('types-generator.commons'))
        );
    });

    afterEach(function () {
        if (File::exists($this->tempPath)) {
            File::deleteDirectory($this->tempPath);
        }
    });

    it('debugs the complete commons generation process', function () {
        $types = [
            'User' => [
                'group' => 'users',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'source' => 'UserResource',
                'config' => (object) ['name' => 'User', 'group' => 'users'],
            ],
            'UserProfile' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'UserProfile', 'group' => 'events'],
            ],
            'UserData' => [
                'group' => 'profiles',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'source' => 'ProfileResource',
                'config' => (object) ['name' => 'UserData', 'group' => 'profiles'],
            ],
        ];

        // Test step by step
        $registry = new TypeRegistry;
        foreach ($types as $typeName => $typeData) {
            $registry->registerType($typeName, $typeData['structure'], $typeData['group'], $typeData['source']);
        }

        $duplicates = $registry->findDuplicates();
        expect($duplicates)->toBeArray();

        $commonTypes = $registry->getCommonTypes(2);
        expect($commonTypes)->toBeArray();

        $extractor = new CommonTypesExtractor(config('types-generator.commons'));
        $extractedTypes = $extractor->extractCommonTypes($registry);
        expect($extractedTypes)->toBeArray();

        // Now run the full generation
        $results = $this->generator->generateFiles($types);
        expect($results)->toBeArray();

        // Check specific files
        $commonExists = File::exists($this->tempPath.'/common.ts');
        $indexExists = File::exists($this->tempPath.'/index.ts');

        expect($commonExists)->toBeTrue();
        expect($indexExists)->toBeTrue();
    });
});
