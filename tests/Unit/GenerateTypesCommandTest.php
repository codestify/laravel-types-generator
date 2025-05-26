<?php

use Codemystify\TypesGenerator\Services\TypeGeneratorService;

describe('GenerateTypesCommand', function () {
    it('can run without options', function () {
        $service = Mockery::mock(TypeGeneratorService::class);
        $service->shouldReceive('generateTypes')->with([
            'force' => false,
            'group' => null,
            'watch' => false,
        ])->andReturn([]);

        $this->app->instance(TypeGeneratorService::class, $service);

        $this->artisan('generate:types')
            ->expectsOutput('🚀 Starting TypeScript types generation...')
            ->expectsOutput('✅ TypeScript types generated successfully!')
            ->assertExitCode(0);
    });

    it('can run with force option', function () {
        $service = Mockery::mock(TypeGeneratorService::class);
        $service->shouldReceive('generateTypes')->with([
            'force' => true,
            'group' => null,
            'watch' => false,
        ])->andReturn([]);

        $this->app->instance(TypeGeneratorService::class, $service);

        $this->artisan('generate:types --force')
            ->assertExitCode(0);
    });

    it('can run with group option', function () {
        $service = Mockery::mock(TypeGeneratorService::class);
        $service->shouldReceive('generateTypes')->with([
            'force' => false,
            'group' => 'events',
            'watch' => false,
        ])->andReturn([]);

        $this->app->instance(TypeGeneratorService::class, $service);

        $this->artisan('generate:types --group=events')
            ->assertExitCode(0);
    });

    it('handles service exceptions gracefully', function () {
        $service = Mockery::mock(TypeGeneratorService::class);
        $service->shouldReceive('generateTypes')
            ->andThrow(new Exception('Test error'));

        $this->app->instance(TypeGeneratorService::class, $service);

        $this->artisan('generate:types')
            ->expectsOutput('❌ Failed to generate types: Test error')
            ->assertExitCode(1);
    });
});
