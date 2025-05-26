<?php

namespace Codemystify\TypesGenerator\Services;

use Codemystify\TypesGenerator\Contracts\SchemaAnalyzerInterface;
use Codemystify\TypesGenerator\Exceptions\SchemaAnalysisException;
use Codemystify\TypesGenerator\Utils\PathResolver;
use Codemystify\TypesGenerator\Utils\TypeMapper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class MigrationAnalyzer implements SchemaAnalyzerInterface
{
    private array $config;

    private array $schemaCache = [];

    public function __construct()
    {
        $this->config = config('types-generator');
    }

    public function analyzeAllMigrations(): array
    {
        if ($this->shouldUseCache()) {
            return $this->getCachedSchema();
        }

        try {
            $migrationFiles = $this->getMigrationFiles();
            $schema = [];

            foreach ($migrationFiles as $file) {
                $analysis = $this->analyzeMigrationFile($file);
                $schema = array_merge_recursive($schema, $analysis);
            }

            $this->cacheSchema($schema);

            return $schema;

        } catch (\Exception $e) {
            throw SchemaAnalysisException::migrationParsingFailed('', $e->getMessage());
        }
    }

    public function getTableSchema(string $tableName): ?array
    {
        $schema = $this->analyzeAllMigrations();

        return $schema[$tableName] ?? null;
    }

    public function clearCache(): void
    {
        $this->schemaCache = [];
        if ($this->config['performance']['cache_enabled'] ?? true) {
            Cache::forget('types_generator_schema');
        }
    }

    private function shouldUseCache(): bool
    {
        return ($this->config['performance']['cache_enabled'] ?? true)
            && ! empty($this->schemaCache);
    }

    private function getCachedSchema(): array
    {
        if (! empty($this->schemaCache)) {
            return $this->schemaCache;
        }

        if ($this->config['performance']['cache_enabled'] ?? true) {
            $cached = Cache::get('types_generator_schema');
            if ($cached) {
                $this->schemaCache = $cached;

                return $cached;
            }
        }

        return [];
    }

    private function cacheSchema(array $schema): void
    {
        $this->schemaCache = $schema;

        if ($this->config['performance']['cache_enabled'] ?? true) {
            $ttl = $this->config['performance']['cache_ttl'] ?? 3600;
            Cache::put('types_generator_schema', $schema, $ttl);
        }
    }

    private function getMigrationFiles(): array
    {
        $path = PathResolver::resolve($this->config['sources']['migrations_path']);

        if (! is_dir($path)) {
            return [];
        }

        return collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->sortBy(fn ($file) => $file->getFilename())
            ->values()
            ->all();
    }

    private function analyzeMigrationFile($file): array
    {
        try {
            $content = File::get($file->getPathname());
            $tableName = $this->extractTableName($content, $file->getFilename());

            if (! $tableName) {
                return [];
            }

            return [
                $tableName => [
                    'columns' => $this->extractColumns($content),
                    'indexes' => $this->extractIndexes($content),
                    'foreign_keys' => $this->extractForeignKeys($content),
                    'file' => $file->getFilename(),
                ],
            ];

        } catch (\Exception $e) {
            throw SchemaAnalysisException::migrationParsingFailed($file->getFilename(), $e->getMessage());
        }
    }

    private function extractTableName(string $content, string $filename): ?string
    {
        // Try to find Schema::create calls
        if (preg_match('/Schema::create\([\'"]([^\'\"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }

        // Try to find Schema::table calls
        if (preg_match('/Schema::table\([\'"]([^\'\"]+)[\'"]/', $content, $matches)) {
            return $matches[1];
        }

        // Fallback to filename analysis
        if (preg_match('/\d{4}_\d{2}_\d{2}_\d{6}_create_(.+)_table\.php$/', $filename, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractColumns(string $content): array
    {
        $columns = [];

        // Common column patterns
        $patterns = [
            '/\$table->(\w+)\([\'"]([^\'\"]+)[\'"](?:,\s*(\d+))?\)(?:->(\w+)\(\))*/' => 'typed',
            '/\$table->(\w+)\(\)(?:->(\w+)\(\))*/' => 'untyped',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if ($type === 'typed') {
                        $columns[$match[2]] = [
                            'type' => TypeMapper::mapColumnType($match[1]),
                            'length' => $match[3] ?? null,
                            'nullable' => str_contains($match[0], '->nullable()'),
                            'default' => $this->extractDefault($match[0]),
                        ];
                    } else {
                        $columnName = $this->inferColumnName($match[1]);
                        if ($columnName) {
                            $columns[$columnName] = [
                                'type' => TypeMapper::mapColumnType($match[1]),
                                'nullable' => str_contains($match[0], '->nullable()'),
                                'default' => $this->extractDefault($match[0]),
                            ];
                        }
                    }
                }
            }
        }

        return $columns;
    }

    private function inferColumnName(string $type): ?string
    {
        return match ($type) {
            'id' => 'id',
            'timestamps' => null, // Handled specially
            'softDeletes' => 'deleted_at',
            'rememberToken' => 'remember_token',
            default => null
        };
    }

    private function extractDefault(string $columnDefinition): mixed
    {
        if (preg_match('/->default\(([^)]+)\)/', $columnDefinition, $matches)) {
            $default = trim($matches[1], '\'"');

            if ($default === 'true') {
                return true;
            }
            if ($default === 'false') {
                return false;
            }
            if (is_numeric($default)) {
                return str_contains($default, '.') ? (float) $default : (int) $default;
            }
            if ($default === 'null') {
                return null;
            }

            return $default;
        }

        return null;
    }

    private function extractIndexes(string $content): array
    {
        $indexes = [];

        $patterns = [
            '/\$table->index\([\'"]([^\'\"]+)[\'"]/' => 'index',
            '/\$table->unique\([\'"]([^\'\"]+)[\'"]/' => 'unique',
            '/\$table->primary\([\'"]([^\'\"]+)[\'"]/' => 'primary',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $column) {
                    $indexes[] = [
                        'column' => $column,
                        'type' => $type,
                    ];
                }
            }
        }

        return $indexes;
    }

    private function extractForeignKeys(string $content): array
    {
        $foreignKeys = [];

        if (preg_match_all('/\$table->foreign\([\'"]([^\'\"]+)[\'"]\)->references\([\'"]([^\'\"]+)[\'"]\)->on\([\'"]([^\'\"]+)[\'"]/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'column' => $match[1],
                    'references' => $match[2],
                    'table' => $match[3],
                ];
            }
        }

        return $foreignKeys;
    }
}
