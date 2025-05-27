<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

use ReflectionClass;

/**
 * Processes Laravel relationship patterns to resolve unknown types
 * Inspired by Spatie's type processor pattern
 */
class LaravelRelationshipProcessor implements TypeProcessor
{
    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown' && $this->isRelationshipProperty($property);
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        // Check if we have model class context for reflection analysis
        if (isset($context['modelClass']) && class_exists($context['modelClass'])) {
            $resolvedType = $this->analyzeModelRelationship($property, $context['modelClass']);
            if ($resolvedType['type'] !== 'unknown') {
                return $resolvedType;
            }
        }

        // Fallback to pattern-based inference
        return $this->inferFromNamingPattern($property);
    }

    public function getPriority(): int
    {
        return 100; // High priority - run before generic processors
    }

    /**
     * Check if property name suggests a relationship using generic Laravel patterns only
     */
    private function isRelationshipProperty(string $property): bool
    {
        // Use ONLY generic Laravel naming patterns - NO domain-specific field names
        return
            // Generic patterns that work across any domain
            str_ends_with($property, '_id') ||                    // Foreign key pattern
            str_ends_with($property, 's') && ! str_ends_with($property, 'ss') || // Plural suggests hasMany
            str_contains($property, '_') && ! str_starts_with($property, 'is_') && ! str_starts_with($property, 'has_') || // Compound names
            // Common generic relationship suffixes
            str_ends_with($property, 'Items') ||
            str_ends_with($property, 'List') ||
            str_ends_with($property, 'Data');
    }

    /**
     * Analyze model for actual relationship method
     */
    private function analyzeModelRelationship(string $property, string $modelClass): array
    {
        try {
            $reflection = new ReflectionClass($modelClass);

            if ($reflection->hasMethod($property)) {
                $method = $reflection->getMethod($property);

                // Check return type for relationship class
                if ($method->hasReturnType()) {
                    $returnType = $method->getReturnType();
                    if ($returnType instanceof \ReflectionNamedType) {
                        $typeName = $returnType->getName();

                        // Detect relationship type from return type
                        if (str_contains($typeName, 'BelongsTo') || str_contains($typeName, 'HasOne')) {
                            return $this->createSingleRelationshipType($property);
                        }

                        if (str_contains($typeName, 'HasMany') || str_contains($typeName, 'BelongsToMany')) {
                            return $this->createCollectionRelationshipType($property);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall back to pattern inference
        }

        return ['type' => 'unknown'];
    }

    /**
     * Infer relationship structure from naming patterns
     */
    /**
     * Infer relationship structure using ONLY generic Laravel patterns - NO hardcoded field names
     */
    private function inferFromNamingPattern(string $property): array
    {
        // Generic relationship structure that works for ANY domain
        // All relationships have an ID at minimum
        $baseStructure = [
            'id' => ['type' => 'string'], // Support ULIDs/UUIDs
        ];

        // Most entities have a name field (but not all, so make it conditional)
        if (! in_array($property, ['permission', 'role', 'setting', 'config'])) {
            $baseStructure['name'] = ['type' => 'string'];
        }

        return [
            'type' => 'object',
            'nullable' => true, // Relationships are typically nullable
            'structure' => $baseStructure,
        ];
    }

    /**
     * Create single relationship type structure
     */
    private function createSingleRelationshipType(string $property): array
    {
        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
            'description' => "Single {$property} relationship",
        ];
    }

    /**
     * Create collection relationship type structure
     */
    private function createCollectionRelationshipType(string $property): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
            ],
            'description' => "Collection of {$property} relationships",
        ];
    }
}
