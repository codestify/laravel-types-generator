<?php

use Codemystify\TypesGenerator\Services\FileTypeDetector;

describe('FileTypeDetector', function () {
    beforeEach(function () {
        $this->detector = new FileTypeDetector;
    });

    it('detects resource file types', function () {
        $filePath = 'app/Http/Resources/UserResource.php';
        $result = $this->detector->detectFileType($filePath, 'UserResource');

        expect($result)->toBe('resource');
    });

    it('detects controller file types', function () {
        $filePath = 'app/Http/Controllers/UserController.php';
        $result = $this->detector->detectFileType($filePath, 'UserController');

        expect($result)->toBe('controller');
    });

    it('detects model file types', function () {
        $filePath = 'app/Models/User.php';
        $result = $this->detector->detectFileType($filePath, 'User');

        expect($result)->toBe('model');
    });

    it('returns unknown for unrecognized paths', function () {
        $filePath = 'some/random/path/MyClass.php';
        $result = $this->detector->detectFileType($filePath, 'MyClass');

        expect($result)->toBe('unknown');
    });

    it('gets correct output paths', function () {
        expect($this->detector->getOutputPath('resource'))->toBe('resources/js/types/resources/')
            ->and($this->detector->getOutputPath('controller'))->toBe('resources/js/types/controllers/')
            ->and($this->detector->getOutputPath('model'))->toBe('resources/js/types/models/')
            ->and($this->detector->getOutputPath('unknown'))->toBe('resources/js/types/generated/');
    });

    it('provides type categories', function () {
        $categories = $this->detector->getTypeCategories();

        expect($categories)->toContain('resource')
            ->and($categories)->toContain('controller')
            ->and($categories)->toContain('model');
    });
});
