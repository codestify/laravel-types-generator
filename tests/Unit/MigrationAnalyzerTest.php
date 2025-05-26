<?php

use Codemystify\TypesGenerator\Services\MigrationAnalyzer;

describe('MigrationAnalyzer', function () {

    it('can analyze empty migrations directory', function () {
        // Create a real temporary directory for this test
        $tempDir = sys_get_temp_dir().'/migration-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        // Update config to use our temp directory
        config(['types-generator.sources.migrations_path' => $tempDir]);

        $analyzer = new MigrationAnalyzer;
        $result = $analyzer->analyzeAllMigrations();

        expect($result)->toBe([]);

        // Cleanup
        rmdir($tempDir);
    });

    it('can extract table name from Schema::create', function () {
        // Create a real temporary directory for this test
        $tempDir = sys_get_temp_dir().'/migration-test-'.uniqid();
        mkdir($tempDir, 0755, true);

        // Create a real migration file
        $migrationContent = createMockMigrationContent('users');
        $migrationFile = $tempDir.'/2024_01_01_000000_create_users_table.php';
        file_put_contents($migrationFile, $migrationContent);

        // Update config to use our temp directory
        config(['types-generator.sources.migrations_path' => $tempDir]);

        $analyzer = new MigrationAnalyzer;
        $result = $analyzer->analyzeAllMigrations();

        expect($result)->toHaveKey('users')
            ->and($result['users'])->toHaveKeys(['columns', 'indexes', 'foreign_keys', 'file']);

        // Cleanup
        unlink($migrationFile);
        rmdir($tempDir);
    });

    it('handles missing migration directory gracefully', function () {
        // Use a non-existent directory
        config(['types-generator.sources.migrations_path' => '/non/existent/path']);

        $analyzer = new MigrationAnalyzer;

        expect($analyzer)->toBeInstanceOf(MigrationAnalyzer::class)
            ->and($analyzer->analyzeAllMigrations())->toBeArray()
            ->and($analyzer->analyzeAllMigrations())->toBe([]);
    });
});
