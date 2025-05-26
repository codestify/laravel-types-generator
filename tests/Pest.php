<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

uses(Codemystify\TypesGenerator\Tests\TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Enhanced Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidTypescriptType', function () {
    $validTypes = ['string', 'number', 'boolean', 'object', 'array', 'null', 'unknown', 'any'];

    return $this->toBeIn($validTypes);
});

expect()->extend('toHaveValidStructure', function () {
    return $this->toBeArray()
        ->and($this->value)->toHaveKeys(['type'])
        ->and($this->value['type'])->toBeString();
});

expect()->extend('toBeValidTypeScriptInterface', function () {
    return $this->toMatch('/^export interface \w+ \{[\s\S]*\}$/');
});

expect()->extend('toContainValidExports', function () {
    return $this->toMatch('/export\s+(interface|type|const)\s+\w+/')
        ->and($this->value)->not->toContain('export {};');
});

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function createMockEvent(array $overrides = []): object
{
    return (object) array_merge([
        'id' => 1,
        'title' => 'Sample Event',
        'description' => 'Sample description',
        'start_date' => '2024-03-15T18:30:00Z',
        'end_date' => '2024-03-15T20:30:00Z',
        'is_active' => true,
        'price' => 25.99,
        'created_at' => '2024-03-01T10:00:00Z',
        'updated_at' => '2024-03-01T10:00:00Z',
    ], $overrides);
}

function createMockUser(array $overrides = []): object
{
    return (object) array_merge([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'email_verified_at' => '2024-01-01T10:00:00Z',
        'created_at' => '2024-01-01T10:00:00Z',
        'updated_at' => '2024-01-01T10:00:00Z',
    ], $overrides);
}

function createComplexMockData(): object
{
    return (object) [
        'id' => 1,
        'nested_object' => (object) [
            'sub_id' => 2,
            'sub_name' => 'Nested',
            'deep_nested' => (object) ['value' => 'deep'],
        ],
        'array_field' => [1, 2, 3],
        'object_array' => [
            (object) ['id' => 1, 'name' => 'Item 1'],
            (object) ['id' => 2, 'name' => 'Item 2'],
        ],
        'mixed_types' => ['string_value', 123, true, null],
    ];
}

function createMockMigrationContent(string $tableName, array $columns = []): string
{
    $defaultColumns = [
        'id' => '$table->id();',
        'title' => '$table->string(\'title\');',
        'created_at' => '$table->timestamps();',
    ];

    $allColumns = array_merge($defaultColumns, $columns);
    $columnDefinitions = implode("\n            ", array_values($allColumns));

    return "<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
    public function up() {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            {$columnDefinitions}
        });
    }
};";
}
