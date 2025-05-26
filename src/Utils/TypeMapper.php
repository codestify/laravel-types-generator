<?php

namespace Codemystify\TypesGenerator\Utils;

class TypeMapper
{
    public static function mapPhpToTypeScript(string $phpType): string
    {
        $map = [
            'int' => 'number', 'integer' => 'number', 'float' => 'number',
            'double' => 'number', 'decimal' => 'number', 'bool' => 'boolean',
            'boolean' => 'boolean', 'string' => 'string', 'array' => 'any[]',
            'object' => 'Record<string, any>', 'null' => 'null', 'mixed' => 'any',
        ];

        return $map[strtolower($phpType)] ?? 'any';
    }

    public static function mapLaravelToTypeScript(string $laravelType): string
    {
        $map = [
            'id' => 'number', 'bigincrements' => 'number', 'increments' => 'number',
            'string' => 'string', 'text' => 'string', 'longtext' => 'string', 'mediumtext' => 'string',
            'integer' => 'number', 'biginteger' => 'number', 'tinyinteger' => 'number', 'smallinteger' => 'number',
            'boolean' => 'boolean', 'decimal' => 'number', 'float' => 'number', 'double' => 'number',
            'date' => 'string', 'datetime' => 'string', 'timestamp' => 'string', 'time' => 'string',
            'json' => 'Record<string, any>', 'jsonb' => 'Record<string, any>',
            'uuid' => 'string', 'ulid' => 'string', 'enum' => 'string',
        ];

        return $map[strtolower($laravelType)] ?? 'any';
    }

    public static function mapColumnType(string $laravelType): string
    {
        return match ($laravelType) {
            'id', 'bigIncrements', 'increments' => 'integer',
            'string', 'text', 'longText', 'mediumText' => 'string',
            'integer', 'bigInteger', 'tinyInteger', 'smallInteger' => 'integer',
            'boolean' => 'boolean',
            'decimal', 'float', 'double' => 'decimal',
            'date', 'datetime', 'timestamp', 'time' => 'datetime',
            'json', 'jsonb' => 'json',
            'enum' => 'enum',
            'uuid' => 'string',
            'ulid' => 'string',
            'timestamps' => 'timestamps',
            default => 'string'
        };
    }

    public static function inferTypeFromValue(mixed $value): array
    {
        return match (true) {
            is_null($value) => ['type' => 'null', 'nullable' => true],
            is_bool($value) => ['type' => 'boolean'],
            is_int($value) => ['type' => 'number'],
            is_float($value) => ['type' => 'number'],
            is_string($value) => ['type' => 'string'],
            is_array($value) => self::analyzeArrayType($value),
            is_object($value) => ['type' => 'object'],
            default => ['type' => 'any']
        };
    }

    private static function analyzeArrayType(array $value): array
    {
        if (empty($value)) {
            return ['type' => 'array', 'items' => ['type' => 'any']];
        }

        $isAssociative = array_keys($value) !== range(0, count($value) - 1);

        if ($isAssociative) {
            return ['type' => 'object', 'structure' => []];
        }

        $firstItem = reset($value);

        return ['type' => 'array', 'items' => self::inferTypeFromValue($firstItem)];
    }
}
