<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;
use Illuminate\Support\Facades\File;

describe('TypeScriptGenerator', function () {

    beforeEach(function () {
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('makeDirectory')->andReturn(true);
        File::shouldReceive('put')->andReturn(true);
        File::shouldReceive('deleteDirectory')->andReturn(true);
    });

    it('can generate basic TypeScript interface', function () {
        $types = [
            'User' => [
                'config' => (object) [
                    'name' => 'User',
                    'group' => 'auth',
                    'description' => 'User model type',
                ],
                'structure' => [
                    'id' => ['type' => 'number'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'is_active' => ['type' => 'boolean'],
                ],
                'source' => 'UserResource::toArray',
                'group' => 'auth',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $results = $generator->generateFiles($types);

        expect($results)->toHaveCount(1)
            ->and($results[0])->toHaveKeys(['name', 'file', 'types_count', 'status'])
            ->and($results[0]['name'])->toBe('auth')
            ->and($results[0]['types_count'])->toBe(1)
            ->and($results[0]['status'])->toBeTrue();
    });

    it('can handle nullable fields', function () {
        $types = [
            'Event' => [
                'config' => (object) [
                    'name' => 'Event',
                    'group' => 'events',
                    'description' => 'Event model type',
                ],
                'structure' => [
                    'id' => ['type' => 'number'],
                    'description' => ['type' => 'string', 'nullable' => true],
                ],
                'source' => 'EventResource::toArray',
                'group' => 'events',
            ],
        ];

        $generator = new TypeScriptGenerator;
        $results = $generator->generateFiles($types);

        expect($results)->toHaveCount(1);
    });
});
