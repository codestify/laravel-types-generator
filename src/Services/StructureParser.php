<?php

namespace Codemystify\TypesGenerator\Services;

class StructureParser
{
    private array $primitiveTypes;

    public function __construct()
    {
        $this->primitiveTypes = config('types-generator.primitive_types', [
            'string', 'number', 'boolean', 'any', 'unknown', 'void',
        ]);
    }

    public function parse(array $structure, array $typeRegistry): array
    {
        if (empty($structure)) {
            return [];
        }

        $parsed = [];

        foreach ($structure as $key => $definition) {
            if (! is_string($key) || trim($key) === '') {
                continue; // Skip invalid keys
            }

            try {
                $parsed[$key] = $this->parseTypeDefinition($definition, $typeRegistry);
            } catch (\InvalidArgumentException $e) {
                // Log warning and skip invalid definitions
                error_log("Invalid type definition for key '{$key}': ".$e->getMessage());

                continue;
            }
        }

        return $parsed;
    }

    private function parseTypeDefinition(mixed $definition, array $typeRegistry): array
    {
        // Handle null or empty definitions
        if ($definition === null || $definition === '') {
            return [
                'type' => 'unknown',
                'isArray' => false,
                'optional' => true,
                'nullable' => true,
            ];
        }

        // Handle string definitions like 'string', 'CustomType', 'CustomType[]'
        if (is_string($definition)) {
            return $this->parseStringType($definition, $typeRegistry);
        }

        // Handle complex definitions like ['type' => 'string', 'optional' => true]
        if (is_array($definition) && isset($definition['type'])) {
            return $this->parseComplexType($definition, $typeRegistry);
        }

        // Handle boolean, number, or other primitive values as fallback
        if (is_bool($definition)) {
            return [
                'type' => 'boolean',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ];
        }

        if (is_numeric($definition)) {
            return [
                'type' => 'number',
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ];
        }

        throw new \InvalidArgumentException('Invalid type definition: '.json_encode($definition));
    }

    private function parseStringType(string $type, array $typeRegistry): array
    {
        $type = trim($type);

        if (empty($type)) {
            return [
                'type' => 'unknown',
                'isArray' => false,
                'optional' => true,
                'nullable' => true,
            ];
        }

        // Handle arrays like 'CustomType[]'
        if (str_ends_with($type, '[]')) {
            $baseType = trim(substr($type, 0, -2));

            if (empty($baseType)) {
                throw new \InvalidArgumentException('Invalid array type definition: '.$type);
            }

            return [
                'type' => $this->resolveTypeReference($baseType, $typeRegistry),
                'isArray' => true,
                'optional' => false,
                'nullable' => false,
            ];
        }

        // Handle union types like 'string|null'
        if (str_contains($type, '|null')) {
            $baseType = trim(str_replace('|null', '', $type));

            if (empty($baseType)) {
                throw new \InvalidArgumentException('Invalid nullable type definition: '.$type);
            }

            return [
                'type' => $this->resolveTypeReference($baseType, $typeRegistry),
                'isArray' => false,
                'optional' => false,
                'nullable' => true,
            ];
        }

        // Handle complex union types like 'string|number'
        if (str_contains($type, '|') && ! str_contains($type, '|null')) {
            return [
                'type' => $type, // Keep original union type
                'isArray' => false,
                'optional' => false,
                'nullable' => false,
            ];
        }

        // Handle simple types
        return [
            'type' => $this->resolveTypeReference($type, $typeRegistry),
            'isArray' => false,
            'optional' => false,
            'nullable' => false,
        ];
    }

    private function parseComplexType(array $definition, array $typeRegistry): array
    {
        return [
            'type' => $this->resolveTypeReference($definition['type'], $typeRegistry),
            'isArray' => isset($definition['isArray']) ? $definition['isArray'] : false,
            'optional' => isset($definition['optional']) ? $definition['optional'] : false,
            'nullable' => isset($definition['nullable']) ? $definition['nullable'] : false,
        ];
    }

    private function resolveTypeReference(string $type, array $typeRegistry): string
    {
        // Check if it's a custom type in registry
        if (isset($typeRegistry[$type])) {
            return $type; // Reference to custom type
        }

        // Check if it's a primitive type
        if (in_array($type, $this->primitiveTypes)) {
            return $type;
        }

        // If not found in registry and not primitive, assume it's a custom type
        return $type;
    }
}
