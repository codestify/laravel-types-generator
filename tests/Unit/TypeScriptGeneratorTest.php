<?php

use Codemystify\TypesGenerator\Services\TypeScriptGenerator;

describe('TypeScriptGenerator', function () {
    beforeEach(function () {
        $this->generator = new TypeScriptGenerator;
    });

    it('generates basic TypeScript interface', function () {
        $structure = [
            'id' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
            'name' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $result = $this->generator->generateInterface('User', $structure);

        expect($result)->toContain('export interface User {')
            ->and($result)->toContain('id: string;')
            ->and($result)->toContain('name: string;')
            ->and($result)->toContain('}');
    });

    it('handles optional and nullable properties', function () {
        $structure = [
            'id' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
            'avatar' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => true,
                'nullable' => false,
            ],
            'email' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => true,
            ],
        ];

        $result = $this->generator->generateInterface('User', $structure);

        expect($result)->toContain('id: string;')
            ->and($result)->toContain('avatar?: string;')
            ->and($result)->toContain('email: string | null;');
    });

    it('handles array types correctly', function () {
        $structure = [
            'tags' => [
                'type' => 'string',
                'isArray' => true,
                'optional' => false,
                'nullable' => false,
            ],
            'comments' => [
                'type' => 'Comment',
                'isArray' => true,
                'optional' => true,
                'nullable' => false,
            ],
        ];

        $result = $this->generator->generateInterface('Post', $structure);

        expect($result)->toContain('tags: string[];')
            ->and($result)->toContain('comments?: Comment[];');
    });

    it('generates interface with extensions', function () {
        $structure = [
            'extraField' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $result = $this->generator->generateInterface('ExtendedUser', $structure, ['BaseUser', 'Timestamps']);

        expect($result)->toContain('export interface ExtendedUser extends BaseUser, Timestamps {');
    });

    it('generates type aliases with union types', function () {
        $result = $this->generator->generateType('Status', [], ['active', 'inactive', 'pending']);

        expect($result)->toBe('export type Status = active | inactive | pending;');
    });

    it('handles complex property types with comments', function () {
        $structure = [
            'metadata' => [
                'type' => 'Record<string, any>',
                'isArray' => false,
                'optional' => true,
                'nullable' => false,
                'frequency' => 5,
                'semantic' => 'metadata',
            ],
        ];

        $result = $this->generator->generateInterface('Entity', $structure);

        expect($result)->toContain('metadata?: Record<string, any>; // Used in 5 types, Semantic: metadata');
    });

    it('generates file content with imports', function () {
        $interfaces = ['export interface User { id: string; }'];
        $imports = ["import type { BaseEntity } from './common';"];

        $result = $this->generator->generateFileContent($interfaces, [], $imports);

        expect($result)->toContain("import type { BaseEntity } from './common';")
            ->and($result)->toContain('export interface User { id: string; }');
    });

    it('handles empty structure gracefully', function () {
        $result = $this->generator->generateInterface('Empty', []);

        expect($result)->toContain('export interface Empty {')
            ->and($result)->toContain('}');
    });

    it('generates optimized interface with common type usage', function () {
        $structure = [
            'id' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
            'created_at' => [
                'type' => 'string',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
            'custom_field' => [
                'type' => 'number',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ],
        ];

        $commonTypeUsage = [
            'BaseEntity' => [
                'id' => [
                    'type' => 'string',
                    'isArray' => false,
                    'optional' => false,
                    'nullable' => false,
                ],
                'created_at' => [
                    'type' => 'string',
                    'isArray' => false,
                    'optional' => false,
                    'nullable' => false,
                ],
            ],
        ];

        $result = $this->generator->generateOptimizedInterface('CustomEntity', $structure, $commonTypeUsage);

        expect($result)->toContain('export interface CustomEntity extends BaseEntity {')
            ->and($result)->toContain('custom_field: number;')
            ->and($result)->not()->toContain('id: string;')
            ->and($result)->not()->toContain('created_at: string;');
    });
});
