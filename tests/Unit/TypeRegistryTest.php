<?php

use Codemystify\TypesGenerator\Services\TypeRegistry;

describe('TypeRegistry', function () {
    beforeEach(function () {
        $this->registry = new TypeRegistry;
    });

    it('can register a type', function () {
        $structure = ['id' => ['type' => 'string'], 'name' => ['type' => 'string']];

        $this->registry->registerType('User', $structure, 'users', 'UserResource');

        expect($this->registry->hasType('User'))->toBeTrue();
        expect($this->registry->getType('User'))->not->toBeNull();
    });

    it('can detect duplicate types', function () {
        $structure = ['id' => ['type' => 'string'], 'name' => ['type' => 'string']];

        $this->registry->registerType('User', $structure, 'users', 'UserResource');
        $this->registry->registerType('UserData', $structure, 'events', 'EventResource');

        $duplicates = $this->registry->findDuplicates();

        expect($duplicates)->toHaveCount(1);
        expect(array_values($duplicates)[0])->toContain('User', 'UserData');
    });

    it('can identify common types across groups', function () {
        $structure = ['id' => ['type' => 'string'], 'name' => ['type' => 'string']];

        $this->registry->registerType('User', $structure, 'users', 'UserResource');
        $this->registry->registerType('UserData', $structure, 'events', 'EventResource');
        $this->registry->registerType('UserInfo', $structure, 'organizations', 'OrgResource');

        $commonTypes = $this->registry->getCommonTypes(2);

        expect($commonTypes)->toHaveCount(1);
        $commonType = array_values($commonTypes)[0];
        expect($commonType['groups'])->toContain('users', 'events', 'organizations');
    });

    it('generates consistent fingerprints for identical structures', function () {
        $structure1 = ['id' => ['type' => 'string'], 'name' => ['type' => 'string']];
        $structure2 = ['name' => ['type' => 'string'], 'id' => ['type' => 'string']]; // Different order

        $this->registry->registerType('Type1', $structure1, 'group1', 'source1');
        $this->registry->registerType('Type2', $structure2, 'group2', 'source2');

        $type1 = $this->registry->getType('Type1');
        $type2 = $this->registry->getType('Type2');

        expect($type1['fingerprint'])->toBe($type2['fingerprint']);
    });

    it('can get types by group', function () {
        $structure1 = ['id' => ['type' => 'string']];
        $structure2 = ['name' => ['type' => 'string']];

        $this->registry->registerType('User', $structure1, 'users', 'source1');
        $this->registry->registerType('Event', $structure2, 'events', 'source2');
        $this->registry->registerType('Profile', $structure1, 'users', 'source3');

        $userTypes = $this->registry->getTypesByGroup('users');

        expect($userTypes)->toHaveCount(2);
        expect(array_keys($userTypes))->toContain('User', 'Profile');
    });

    it('can clear all types', function () {
        $structure = ['id' => ['type' => 'string']];

        $this->registry->registerType('User', $structure, 'users', 'source');
        expect($this->registry->hasType('User'))->toBeTrue();

        $this->registry->clear();
        expect($this->registry->hasType('User'))->toBeFalse();
        expect($this->registry->getAllTypes())->toBeEmpty();
    });

    it('normalizes type strings consistently', function () {
        $structure1 = ['field' => ['type' => 'string|null']];
        $structure2 = ['field' => ['type' => 'null|string']];

        $this->registry->registerType('Type1', $structure1, 'group1', 'source1');
        $this->registry->registerType('Type2', $structure2, 'group2', 'source2');

        $duplicates = $this->registry->findDuplicates();
        expect($duplicates)->toHaveCount(1);
    });
});
