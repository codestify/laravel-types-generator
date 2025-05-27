<?php

use Codemystify\TypesGenerator\Console\GenerateTypesCommand;
use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;

describe('GenerateTypesCommand', function () {
    it('can be instantiated with dependencies', function () {
        $service = app(SimpleTypeGeneratorService::class);
        $command = new GenerateTypesCommand($service);

        expect($command)->toBeInstanceOf(GenerateTypesCommand::class);
    });

    it('has correct signature', function () {
        $service = app(SimpleTypeGeneratorService::class);
        $command = new GenerateTypesCommand($service);

        expect($command->getName())->toBe('types:generate');
    });
});
