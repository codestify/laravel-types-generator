<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Dynamic Laravel Convention Type Processor
 * Uses Laravel conventions and reflection to infer types without hardcoding
 */
class LaravelConventionProcessor implements TypeProcessor
{
    private array $cache = [];

    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown';
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        // Try multiple inference strategies in order of confidence
        $strategies = [
            fn () => $this->inferFromModelRelationshipMethod($property, $context),
            fn () => $this->inferFromLaravelNamingPatterns($property),
            fn () => $this->inferFromDatabaseSchema($property, $context),
            fn () => $this->inferFromResourceMethod($property, $context),
        ];

        foreach ($strategies as $strategy) {
            $result = $strategy();
            if ($result && $result['type'] !== 'unknown') {
                return $result;
            }
        }

        return null;
    }

    public function getPriority(): int
    {
        return 90; // High priority
    }

    /**
     * Check if a model has a relationship method for this property
     */
    private function inferFromModelRelationshipMethod(string $property, array $context): ?array
    {
        if (! isset($context['modelClass']) || ! class_exists($context['modelClass'])) {
            return null;
        }

        try {
            $modelReflection = new ReflectionClass($context['modelClass']);

            // Check if the property name corresponds to a method
            if (! $modelReflection->hasMethod($property)) {
                return null;
            }

            $method = $modelReflection->getMethod($property);

            // Only analyze public methods (Laravel relationships are public)
            if (! $method->isPublic() || $method->isStatic()) {
                return null;
            }

            // Analyze the method's return type or body to detect relationships
            return $this->analyzeRelationshipMethod($method);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Analyze a method to determine if it's a Laravel relationship
     */
    private function analyzeRelationshipMethod(ReflectionMethod $method): ?array
    {
        // Check return type annotation
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();

            // Laravel relationship return types
            $relationshipTypes = [
                'Illuminate\Database\Eloquent\Relations\HasOne' => 'single',
                'Illuminate\Database\Eloquent\Relations\BelongsTo' => 'single',
                'Illuminate\Database\Eloquent\Relations\MorphOne' => 'single',
                'Illuminate\Database\Eloquent\Relations\MorphTo' => 'single',
                'Illuminate\Database\Eloquent\Relations\HasMany' => 'collection',
                'Illuminate\Database\Eloquent\Relations\BelongsToMany' => 'collection',
                'Illuminate\Database\Eloquent\Relations\MorphMany' => 'collection',
                'Illuminate\Database\Eloquent\Relations\MorphToMany' => 'collection',
            ];

            if (isset($relationshipTypes[$typeName])) {
                return $this->createDynamicRelationshipType($relationshipTypes[$typeName]);
            }
        }

        // If no return type, analyze method body for relationship calls
        return $this->analyzeMethodBodyForRelationships($method);
    }

    /**
     * Analyze method body for Laravel relationship method calls
     */
    private function analyzeMethodBodyForRelationships(ReflectionMethod $method): ?array
    {
        try {
            $filename = $method->getFileName();
            if (! $filename) {
                return null;
            }

            $source = file_get_contents($filename);
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();
            $methodSource = implode("\n", array_slice(explode("\n", $source), $startLine, $endLine - $startLine));

            // Look for Laravel relationship method calls
            $relationshipMethods = [
                'hasOne' => 'single',
                'belongsTo' => 'single',
                'morphOne' => 'single',
                'morphTo' => 'single',
                'hasMany' => 'collection',
                'belongsToMany' => 'collection',
                'morphMany' => 'collection',
                'morphToMany' => 'collection',
            ];

            foreach ($relationshipMethods as $methodName => $type) {
                if (preg_match('/\$this\s*->\s*'.$methodName.'\s*\(/', $methodSource)) {
                    return $this->createDynamicRelationshipType($type);
                }
            }

        } catch (\Exception $e) {
            // Ignore parsing errors
        }

        return null;
    }

    /**
     * Create relationship type structure without hardcoding property names
     */
    private function createDynamicRelationshipType(string $relationType): array
    {
        if ($relationType === 'single') {
            return [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string', 'description' => 'Primary key'],
                    'name' => ['type' => 'string', 'nullable' => true],
                ],
                'description' => 'Single model relationship',
            ];
        }

        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'structure' => [
                    'id' => ['type' => 'string', 'description' => 'Primary key'],
                    'name' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'description' => 'Collection of models',
        ];
    }

    /**
     * Use Laravel naming patterns to infer types (generic, not project-specific)
     */
    private function inferFromLaravelNamingPatterns(string $property): ?array
    {
        // Foreign key pattern
        if (str_ends_with($property, '_id')) {
            return ['type' => 'string', 'description' => 'Foreign key'];
        }

        // Timestamp pattern
        if (str_ends_with($property, '_at')) {
            return ['type' => 'string', 'description' => 'Timestamp'];
        }

        // Boolean pattern
        if (str_starts_with($property, 'is_') || str_starts_with($property, 'has_') || str_starts_with($property, 'can_')) {
            return ['type' => 'boolean'];
        }

        // Primary key patterns
        if (in_array($property, ['id', 'uuid', 'ulid'])) {
            return ['type' => 'string', 'description' => 'Primary key'];
        }

        // Count/number patterns
        if (str_ends_with($property, '_count') || str_ends_with($property, '_total') || str_ends_with($property, '_amount')) {
            return ['type' => 'number'];
        }

        // URL/Path patterns
        if (str_ends_with($property, '_url') || str_ends_with($property, '_path') || str_contains($property, 'link')) {
            return ['type' => 'string', 'description' => 'URL or path'];
        }

        // Image/media patterns (generic)
        if (str_contains($property, 'image') || str_contains($property, 'photo') || str_contains($property, 'avatar')) {
            return [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'url' => ['type' => 'string'],
                    'alt_text' => ['type' => 'string', 'nullable' => true],
                ],
                'description' => 'Image or media object',
            ];
        }

        return null;
    }

    /**
     * Infer from database schema if available
     */
    private function inferFromDatabaseSchema(string $property, array $context): ?array
    {
        if (! isset($context['schemaInfo']) || ! isset($context['tableName'])) {
            return null;
        }

        $schema = $context['schemaInfo'];
        $tableName = $context['tableName'];

        if (! isset($schema[$tableName]['columns'][$property])) {
            return null;
        }

        $column = $schema[$tableName]['columns'][$property];

        return $this->mapDatabaseTypeToTypeScript($column);
    }

    /**
     * Map database column types to TypeScript types
     */
    private function mapDatabaseTypeToTypeScript(array $column): array
    {
        $type = match ($column['type']) {
            'integer', 'bigint', 'smallint', 'tinyint' => 'number',
            'decimal', 'float', 'double' => 'number',
            'string', 'text', 'longtext', 'char', 'varchar' => 'string',
            'boolean' => 'boolean',
            'json', 'jsonb' => 'object',
            'date', 'datetime', 'timestamp' => 'string',
            default => 'string'
        };

        $result = ['type' => $type];

        if ($column['nullable'] ?? false) {
            $result['nullable'] = true;
        }

        if ($type === 'string' && in_array($column['type'], ['date', 'datetime', 'timestamp'])) {
            $result['description'] = 'Date/time string';
        }

        return $result;
    }

    /**
     * Try to infer from Resource method patterns
     */
    private function inferFromResourceMethod(string $property, array $context): ?array
    {
        if (! isset($context['resourceClass'])) {
            return null;
        }

        // Look for accessor methods that might transform this property
        $accessorMethod = 'get'.str_replace('_', '', ucwords($property, '_')).'Attribute';

        try {
            $resourceReflection = new ReflectionClass($context['resourceClass']);

            if ($resourceReflection->hasMethod($accessorMethod)) {
                $method = $resourceReflection->getMethod($accessorMethod);

                return $this->analyzeAccessorMethod($method);
            }
        } catch (\Exception $e) {
            // Ignore reflection errors
        }

        return null;
    }

    /**
     * Analyze Laravel accessor methods to infer return types
     */
    private function analyzeAccessorMethod(ReflectionMethod $method): ?array
    {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            $typeName = $returnType->getName();

            return match ($typeName) {
                'string' => ['type' => 'string'],
                'int', 'integer' => ['type' => 'number'],
                'float', 'double' => ['type' => 'number'],
                'bool', 'boolean' => ['type' => 'boolean'],
                'array' => ['type' => 'array'],
                default => ['type' => 'string']
            };
        }

        return null;
    }
}
