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
     * Check if property name suggests a relationship
     */
    private function isRelationshipProperty(string $property): bool
    {
        $relationshipPatterns = [
            'category', 'eventCategory', 'event_category',
            'organization', 'user', 'owner', 'author',
            'parent', 'company', 'team', 'group',
        ];

        return in_array($property, $relationshipPatterns) ||
               str_ends_with($property, 'Category') ||
               str_ends_with($property, 'Organization');
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
    private function inferFromNamingPattern(string $property): array
    {
        return match ($property) {
            'category', 'eventCategory', 'event_category' => [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string', 'description' => 'Category ULID'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'description' => ['type' => 'string', 'nullable' => true],
                    'color' => ['type' => 'string', 'nullable' => true],
                ],
                'description' => 'Event category relationship',
            ],
            'organization' => [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string', 'description' => 'Organization ULID'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'logo' => ['type' => 'string', 'nullable' => true],
                    'website' => ['type' => 'string', 'nullable' => true],
                ],
                'description' => 'Organization relationship',
            ],
            'user', 'owner', 'author' => [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string', 'description' => 'User ULID'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string'],
                    'avatar' => ['type' => 'string', 'nullable' => true],
                ],
                'description' => 'User relationship',
            ],
            default => ['type' => 'object', 'nullable' => true]
        };
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
