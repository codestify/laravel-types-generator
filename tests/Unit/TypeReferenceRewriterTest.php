<?php

use Codemystify\TypesGenerator\Services\TypeReferenceRewriter;

describe('TypeReferenceRewriter', function () {
    beforeEach(function () {
        $this->rewriter = new TypeReferenceRewriter([
            'file_name' => 'common',
            'import_style' => 'relative',
        ]);
    });

    it('generates import statements correctly', function () {
        $commonTypes = [
            'fingerprint1' => [
                'name' => 'User',
                'structure' => ['id' => ['type' => 'string']],
                'originalNames' => ['User', 'UserData'],
            ],
            'fingerprint2' => [
                'name' => 'Organization',
                'structure' => ['id' => ['type' => 'string']],
                'originalNames' => ['Organization', 'OrgData'],
            ],
        ];

        $imports = $this->rewriter->generateImportStatements($commonTypes, 'events');

        expect($imports)->toContain("import type { Organization, User } from './common';");
    });

    it('rewrites type references correctly', function () {
        $types = [
            'events' => [
                'Event' => [
                    'structure' => [
                        'id' => ['type' => 'string'],
                        'organizer' => ['type' => 'User'],
                    ],
                ],
            ],
            'users' => [
                'User' => [
                    'structure' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $commonTypes = [
            'fingerprint1' => [
                'name' => 'User',
                'structure' => ['id' => ['type' => 'string'], 'name' => ['type' => 'string']],
                'originalNames' => ['User'],
            ],
        ];

        $rewritten = $this->rewriter->rewriteReferences($types, $commonTypes);

        // User should be removed from users group since it's now common
        expect($rewritten['users'])->not->toHaveKey('User');

        // Event should still exist and reference User
        expect($rewritten['events'])->toHaveKey('Event');
    });

    it('identifies type references correctly', function () {
        $result = $this->rewriter->convertToReference('User', [
            'fingerprint1' => [
                'name' => 'User',
                'originalNames' => ['User', 'UserData'],
            ],
        ]);

        expect($result['shouldImport'])->toBeTrue();
        expect($result['importName'])->toBe('User');
    });

    it('handles array types in references', function () {
        $types = [
            'events' => [
                'Event' => [
                    'structure' => [
                        'id' => ['type' => 'string'],
                        'attendees' => ['type' => 'User[]'],
                    ],
                ],
            ],
        ];

        $commonTypes = [
            'fingerprint1' => [
                'name' => 'User',
                'originalNames' => ['User'],
            ],
        ];

        $requiredImports = $this->rewriter->getRequiredImports('events', $types, $commonTypes);

        expect($requiredImports)->toContain('User');
    });

    it('handles union types in references', function () {
        $types = [
            'events' => [
                'Event' => [
                    'structure' => [
                        'id' => ['type' => 'string'],
                        'organizer' => ['type' => 'User | Organization'],
                    ],
                ],
            ],
        ];

        $commonTypes = [
            'fingerprint1' => [
                'name' => 'User',
                'originalNames' => ['User'],
            ],
            'fingerprint2' => [
                'name' => 'Organization',
                'originalNames' => ['Organization'],
            ],
        ];

        $requiredImports = $this->rewriter->getRequiredImports('events', $types, $commonTypes);

        expect($requiredImports)->toContain('User', 'Organization');
    });
});
