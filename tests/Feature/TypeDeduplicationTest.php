<?php

use Codemystify\TypesGenerator\Services\CommonTypesExtractor;
use Codemystify\TypesGenerator\Services\TypeReferenceRewriter;
use Codemystify\TypesGenerator\Services\TypeRegistry;
use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\Facades\File;

describe('Type Deduplication Feature', function () {
    beforeEach(function () {
        $this->tempPath = sys_get_temp_dir().'/types-generator-test-'.uniqid();
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

    it('generates common types file when duplicates exist', function () {
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
            'UserData' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'UserData', 'group' => 'events'],
            ],
            'Event' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'organizer' => ['type' => 'User'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'Event', 'group' => 'events'],
            ],
        ];

        $this->generator->generateFiles($types);

        // Check that common.ts file was created
        expect(File::exists($this->tempPath.'/common.ts'))->toBeTrue();

        // Check that common.ts contains the deduplicated User type
        $commonContent = File::get($this->tempPath.'/common.ts');
        expect($commonContent)->toContain('export interface User');
        expect($commonContent)->toContain('id: string');
        expect($commonContent)->toContain('name: string');
        expect($commonContent)->toContain('email: string');
    });

    it('removes duplicate types from domain files', function () {
        $types = [
            'User' => [
                'group' => 'users',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'source' => 'UserResource',
                'config' => (object) ['name' => 'User', 'group' => 'users'],
            ],
            'UserData' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'UserData', 'group' => 'events'],
            ],
        ];

        $this->generator->generateFiles($types);

        // Check that common.ts was created and contains the deduplicated type
        expect(File::exists($this->tempPath.'/common.ts'))->toBeTrue();
        $commonContent = File::get($this->tempPath.'/common.ts');
        expect($commonContent)->toContain('export interface User');

        // Check that User is not in users.ts (moved to common.ts)
        if (File::exists($this->tempPath.'/users.ts')) {
            $usersContent = File::get($this->tempPath.'/users.ts');
            expect($usersContent)->not->toContain('export interface User');
        }

        // Check that UserData is not in events.ts (moved to common.ts)
        if (File::exists($this->tempPath.'/events.ts')) {
            $eventsContent = File::get($this->tempPath.'/events.ts');
            expect($eventsContent)->not->toContain('export interface UserData');
        }
    });

    it('includes commons in index file', function () {
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
            'UserData' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'UserData', 'group' => 'events'],
            ],
        ];

        $this->generator->generateFiles($types);

        // Check that index.ts includes common types
        expect(File::exists($this->tempPath.'/index.ts'))->toBeTrue();
        $indexContent = File::get($this->tempPath.'/index.ts');
        expect($indexContent)->toContain("export * from './common';");
    });

    it('disables commons when configured', function () {
        config(['types-generator.commons.enabled' => false]);

        $generator = new TypeScriptGenerator(
            new TypeRegistry,
            new CommonTypesExtractor(config('types-generator.commons')),
            new TypeReferenceRewriter(config('types-generator.commons'))
        );

        $types = [
            'User' => [
                'group' => 'users',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'source' => 'UserResource',
                'config' => (object) ['name' => 'User', 'group' => 'users'],
            ],
            'UserData' => [
                'group' => 'events',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
                'source' => 'EventResource',
                'config' => (object) ['name' => 'UserData', 'group' => 'events'],
            ],
        ];

        $generator->generateFiles($types);

        // Common file should not be created
        expect(File::exists($this->tempPath.'/common.ts'))->toBeFalse();
    });
});
