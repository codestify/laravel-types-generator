<?php

namespace Codemystify\TypesGenerator\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionMethod;

class SimpleReflectionAnalyzer
{
    private array $config;

    private AstAnalyzer $astAnalyzer;

    public function __construct()
    {
        $this->config = config('types-generator');
        $this->astAnalyzer = new AstAnalyzer;
    }

    public function analyzeMethod(ReflectionMethod $method, array $schemaInfo): array
    {
        $className = $method->getDeclaringClass()->getName();

        if ($this->isResourceClass($className)) {
            return $this->analyzeResourceMethod($method, $schemaInfo);
        }

        if ($this->isControllerClass($className)) {
            return $this->analyzeControllerMethod($method, $schemaInfo);
        }

        return [];
    }

    private function analyzeResourceMethod(ReflectionMethod $method, array $schemaInfo): array
    {
        if ($method->getName() !== 'toArray') {
            return [];
        }

        try {
            $resourceClass = $method->getDeclaringClass()->getName();

            // Use AST-based analysis for all resources
            $astResult = $this->astAnalyzer->analyzeMethodReturnStructure($method);

            if ($astResult && $astResult['type'] !== 'unknown') {
                // Extract the structure from the AST result
                if ($astResult['type'] === 'object' && isset($astResult['structure'])) {
                    return $this->postProcessUnknownTypes($astResult['structure']);
                }

                return $astResult;
            }

            // Fallback: Try to create a real instance with sample data
            $sampleData = $this->createSampleData($resourceClass, $schemaInfo);

            if ($sampleData) {
                $resource = new $resourceClass($sampleData);
                $output = $resource->toArray(request());
                $analyzed = $this->analyzeArrayStructure($output);

                return $this->postProcessUnknownTypes($analyzed);
            }

            return ['type' => 'unknown'];

        } catch (\Exception $e) {
            return ['type' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    /**
     * Process the analyzed structure with type processors to resolve unknowns
     */
    private function processWithTypeProcessors(array $structure, ReflectionMethod $method, array $schemaInfo): array
    {
        $processors = $this->getTypeProcessors();
        $resourceClass = $method->getDeclaringClass();
        $modelClass = $this->findCorrespondingModel($resourceClass->getName());

        // Build context for processors
        $context = [
            'resourceClass' => $resourceClass->getName(),
            'methodSource' => $this->getMethodSource($method),
            'schemaInfo' => $schemaInfo,
        ];

        if ($modelClass) {
            $context['modelClass'] = $modelClass;
            $context['tableName'] = $this->getTableName($modelClass);
        }

        // Handle different structure formats
        if (isset($structure['structure']) && is_array($structure['structure'])) {
            // Process nested structure
            foreach ($structure['structure'] as $property => &$typeInfo) {
                if (is_array($typeInfo) && isset($typeInfo['type']) && $typeInfo['type'] === 'unknown') {
                    $resolved = $this->resolveWithProcessors($property, $typeInfo, $context, $processors);
                    if ($resolved) {
                        $typeInfo = $resolved;
                    }
                }
            }
        } elseif (is_array($structure)) {
            // Process flat structure (each key is a property with its type info)
            foreach ($structure as $property => &$typeInfo) {
                if (is_array($typeInfo) && isset($typeInfo['type']) && $typeInfo['type'] === 'unknown') {
                    $resolved = $this->resolveWithProcessors($property, $typeInfo, $context, $processors);
                    if ($resolved) {
                        $typeInfo = $resolved;
                    }
                }
            }
        }

        return $structure;
    }

    /**
     * Get all available type processors
     */
    private function getTypeProcessors(): array
    {
        return [
            // Temporarily disable all processors
        ];
    }

    /**
     * Resolve unknown type using processors
     */
    private function resolveWithProcessors(string $property, array $currentType, array $context, array $processors): ?array
    {
        // Sort processors by priority (higher first)
        usort($processors, fn ($a, $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($processors as $processor) {
            if ($processor->canProcess($property, $currentType, $context)) {
                $result = $processor->process($property, $currentType, $context);
                if ($result && $result['type'] !== 'unknown') {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Get the source code of a method for analysis
     */
    private function getMethodSource(ReflectionMethod $method): ?string
    {
        try {
            $filename = $method->getFileName();
            if (! $filename) {
                return null;
            }

            $source = file_get_contents($filename);
            $startLine = $method->getStartLine() - 1;
            $endLine = $method->getEndLine();

            return implode("\n", array_slice(explode("\n", $source), $startLine, $endLine - $startLine));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function analyzeControllerMethod(ReflectionMethod $method, array $schemaInfo): array
    {
        // Use AST analyzer for controller method analysis too
        return $this->astAnalyzer->analyzeMethodReturnStructure($method);
    }

    private function createSampleData(string $resourceClass, array $schemaInfo): mixed
    {
        // Try to find the corresponding model
        $modelClass = $this->findCorrespondingModel($resourceClass);

        if (! $modelClass || ! class_exists($modelClass)) {
            return $this->createGenericSampleData();
        }

        try {
            // Create a sample model instance with basic data
            $model = new $modelClass;

            // Fill with sample data based on schema
            $tableName = $this->getTableName($modelClass);
            $tableSchema = $schemaInfo[$tableName] ?? null;

            if ($tableSchema) {
                foreach ($tableSchema['columns'] as $column => $columnInfo) {
                    $model->$column = $this->generateSampleValue($columnInfo);
                }
            }

            return $model;

        } catch (\Exception $e) {
            return $this->createGenericSampleData();
        }
    }

    private function createGenericSampleData(): object
    {
        return (object) [
            'id' => 1,
            'title' => 'Sample Event',
            'description' => 'Sample description',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function generateSampleValue(array $columnInfo): mixed
    {
        return match ($columnInfo['type']) {
            'integer' => 1,
            'string' => 'sample_value',
            'boolean' => true,
            'decimal' => 10.50,
            'datetime' => now(),
            'json' => ['sample' => 'data'],
            default => null
        };
    }

    private function analyzeArrayStructure(array $data): array
    {
        $structure = [];

        foreach ($data as $key => $value) {
            $structure[$key] = $this->getTypeFromValue($value);
        }

        return $structure;
    }

    private function getTypeFromValue(mixed $value): array
    {
        if (is_null($value)) {
            return ['type' => 'null', 'nullable' => true];
        }

        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (is_int($value)) {
            return ['type' => 'number'];
        }

        if (is_float($value)) {
            return ['type' => 'number'];
        }

        if (is_string($value)) {
            return ['type' => 'string'];
        }

        if (is_array($value)) {
            if (empty($value)) {
                return ['type' => 'array', 'items' => ['type' => 'unknown']];
            }

            // Check if it's an associative array (object) or indexed array
            if (array_keys($value) !== range(0, count($value) - 1)) {
                // Associative array - treat as object
                return [
                    'type' => 'object',
                    'structure' => $this->analyzeArrayStructure($value),
                ];
            } else {
                // Indexed array
                $firstItem = reset($value);

                return [
                    'type' => 'array',
                    'items' => $this->getTypeFromValue($firstItem),
                ];
            }
        }

        if (is_object($value)) {
            return ['type' => 'object'];
        }

        return ['type' => 'unknown'];
    }

    private function findCorrespondingModel(?string $resourceClass = null): ?string
    {
        if (! $resourceClass) {
            return null;
        }

        // Extract model name from resource class name
        $resourceName = class_basename($resourceClass);
        $modelName = str_replace('Resource', '', $resourceName);

        // Build model class path from configuration
        $modelClass = $this->config['namespaces']['models'].'\\'.$modelName;

        // Verify the model exists and is actually a Laravel model
        if (class_exists($modelClass)) {
            if (is_subclass_of($modelClass, \Illuminate\Database\Eloquent\Model::class)) {
                return $modelClass;
            }

            // If it's not an Eloquent model but class exists, still return it
            return $modelClass;
        }

        return null;
    }

    private function getTableName(string $modelClass): string
    {
        try {
            return (new $modelClass)->getTable();
        } catch (\Exception $e) {
            // Fallback to class name conversion
            $className = class_basename($modelClass);

            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)).'s';
        }
    }

    /**
     * Post-process analyzed structure to fix common unknown types using intelligent pattern analysis
     */
    private function postProcessUnknownTypes(array $structure, ?string $resourceClass = null): array
    {
        foreach ($structure as $property => &$typeInfo) {
            if (is_array($typeInfo)) {
                // Recursively process nested structures
                if (isset($typeInfo['structure']) && is_array($typeInfo['structure'])) {
                    $typeInfo['structure'] = $this->postProcessUnknownTypes($typeInfo['structure'], $resourceClass);
                }

                // Fix null types that should be nullable strings (formatted_ patterns)
                if ($typeInfo['type'] === 'null' && str_starts_with($property, 'formatted_')) {
                    $typeInfo = ['type' => 'string', 'nullable' => true];
                }

                // Fix unknown types with intelligent analysis
                elseif ($typeInfo['type'] === 'unknown') {
                    $typeInfo = $this->intelligentPatternAnalysisWithContext($property, $resourceClass);
                }
            }
        }

        return $structure;
    }

    /**
     * Enhanced pattern analysis with resource context for better accuracy
     */
    private function intelligentPatternAnalysisWithContext(string $property, ?string $resourceClass = null): array
    {
        // Try enhanced analysis with resource context first
        if ($resourceClass && $this->looksLikeRelationshipPropertyWithContext($property, $resourceClass)) {
            return $this->inferRelationshipStructureWithContext($property, $resourceClass);
        }

        // Fall back to generic pattern analysis
        return $this->intelligentPatternAnalysis($property);
    }

    /**
     * Infer relationship structure with actual model context when available
     */
    private function inferRelationshipStructureWithContext(string $property, string $resourceClass): array
    {
        $modelClass = $this->findCorrespondingModel($resourceClass);

        if ($modelClass) {
            // Try to get actual relationship info from the model
            $relationshipFields = $this->analyzeActualRelationship($property, $modelClass);
            if (! empty($relationshipFields)) {
                return [
                    'type' => 'object',
                    'nullable' => true,
                    'structure' => $relationshipFields,
                ];
            }
        }

        // Fall back to generic relationship structure
        return $this->inferRelationshipStructure($property);
    }

    /**
     * Analyze actual relationship from model to get real field structure
     */
    private function analyzeActualRelationship(string $property, string $modelClass): array
    {
        try {
            $model = new $modelClass;
            $reflection = new \ReflectionClass($model);

            if ($reflection->hasMethod($property)) {
                $method = $reflection->getMethod($property);

                try {
                    $relation = $method->invoke($model);
                    if ($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        // Get the related model and analyze its structure
                        $relatedModel = $relation->getRelated();

                        return $this->getBasicModelFields($relatedModel);
                    }
                } catch (\Exception $e) {
                    // Relationship might require data or fail for other reasons
                }
            }
        } catch (\Exception $e) {
            // Model instantiation failed
        }

        return [];
    }

    /**
     * Get basic fields from a model instance (fillable, visible, or common fields)
     */
    private function getBasicModelFields(\Illuminate\Database\Eloquent\Model $model): array
    {
        $fields = ['id' => ['type' => 'string']]; // All models have ID

        // Try to get fillable fields
        $fillable = $model->getFillable();
        if (! empty($fillable)) {
            foreach (array_slice($fillable, 0, 3) as $field) { // Limit to first 3 for brevity
                if (! str_ends_with($field, '_id')) { // Skip foreign keys
                    $fields[$field] = ['type' => 'string'];
                }
            }
        } else {
            // Default minimal structure
            $fields['name'] = ['type' => 'string'];
        }

        return $fields;
    }

    private function isResourceClass(string $className): bool
    {
        return str_contains($className, 'Resource') ||
            is_subclass_of($className, JsonResource::class);
    }

    private function isControllerClass(string $className): bool
    {
        return str_contains($className, 'Controller') ||
            is_subclass_of($className, 'Illuminate\\Routing\\Controller');
    }

    /**
     * Simple dynamic type inference for unknown properties using intelligent Laravel pattern analysis
     */
    private function inferUnknownType(string $property): array
    {
        // Try to intelligently resolve by analyzing Laravel patterns
        return $this->intelligentPatternAnalysis($property);
    }

    /**
     * Intelligent pattern analysis based on generic Laravel conventions (works for any domain)
     */
    private function intelligentPatternAnalysis(string $property): array
    {
        // Pattern 1: Formatted values (getFormattedXxxAttribute pattern)
        if (str_starts_with($property, 'formatted_')) {
            return ['type' => 'string', 'nullable' => true];
        }

        // Pattern 2: Boolean patterns (Laravel accessor conventions)
        if (str_starts_with($property, 'is_') || str_starts_with($property, 'has_') || str_starts_with($property, 'can_')) {
            return ['type' => 'boolean'];
        }

        // Pattern 3: Date/time patterns (Laravel convention)
        if (str_contains($property, 'date') || str_contains($property, 'time') ||
            in_array($property, ['created_at', 'updated_at', 'deleted_at'])) {
            return ['type' => 'string', 'description' => 'Date/time string'];
        }

        // Pattern 4: ID fields (Laravel convention)
        if ($property === 'id' || str_ends_with($property, '_id')) {
            return ['type' => 'string']; // Support for ULIDs/UUIDs
        }

        // Pattern 5: URL/path fields (common web app patterns)
        if (str_contains($property, 'url') || str_contains($property, 'path') || str_contains($property, 'link')) {
            return ['type' => 'string'];
        }

        // Pattern 6: Generic relationship detection
        if ($this->looksLikeRelationshipProperty($property)) {
            return $this->inferRelationshipStructure($property);
        }

        // Default fallback - safe for any domain
        return ['type' => 'string', 'nullable' => true];
    }

    /**
     * Check if property name suggests it's a relationship using dynamic Laravel model analysis
     */
    private function looksLikeRelationshipProperty(string $property): bool
    {
        // Try to find and analyze the actual model to detect real relationships
        // Since we don't have resource class context here, this will return null
        $modelClass = $this->findCorrespondingModel(null);

        if ($modelClass) {
            return $this->isActualModelRelationship($property, $modelClass);
        }

        // If no model found, use generic Laravel naming conventions
        return $this->hasGenericRelationshipPatterns($property);
    }

    /**
     * Check if property is an actual relationship method in the model
     */
    private function isActualModelRelationship(string $property, string $modelClass): bool
    {
        try {
            $model = new $modelClass;
            $reflection = new \ReflectionClass($model);

            // Check if method exists and could be a relationship
            if ($reflection->hasMethod($property)) {
                $method = $reflection->getMethod($property);

                // Skip if method requires parameters (relationships don't)
                if ($method->getNumberOfRequiredParameters() > 0) {
                    return false;
                }

                // Try to invoke and check if it returns a relationship
                try {
                    $result = $method->invoke($model);

                    return $result instanceof \Illuminate\Database\Eloquent\Relations\Relation;
                } catch (\Exception $e) {
                    // Method might fail for other reasons, but that's ok
                    return false;
                }
            }
        } catch (\Exception $e) {
            // Model instantiation or reflection failed
        }

        return false;
    }

    /**
     * Use generic Laravel naming patterns when model analysis isn't available
     */
    private function hasGenericRelationshipPatterns(string $property): bool
    {
        // Generic Laravel relationship patterns that work across domains
        return
            // Plural forms suggest hasMany/belongsToMany relationships
            str_ends_with($property, 's') && ! str_ends_with($property, 'ss') ||

            // Common relationship suffixes across all domains
            str_ends_with($property, '_items') ||
            str_ends_with($property, '_list') ||

            // Compound property names suggest relationships
            str_contains($property, '_') && ! str_starts_with($property, 'is_') && ! str_starts_with($property, 'has_') ||

            // Foreign key patterns suggest belongsTo relationships
            str_ends_with($property, '_id') && strlen($property) > 3;
    }

    /**
     * Enhanced relationship property detection with context
     */
    private function looksLikeRelationshipPropertyWithContext(string $property, string $resourceClass): bool
    {
        $modelClass = $this->findCorrespondingModel($resourceClass);

        if ($modelClass) {
            return $this->isActualModelRelationship($property, $modelClass);
        }

        return $this->hasGenericRelationshipPatterns($property);
    }

    /**
     * Infer structure for relationship-like properties using dynamic analysis
     */
    private function inferRelationshipStructure(string $property): array
    {
        // Base structure for all relationships
        $baseStructure = ['id' => ['type' => 'string']];

        // Dynamically infer additional fields based on property name patterns
        $additionalFields = $this->inferRelationshipFields($property);
        $structure = array_merge($baseStructure, $additionalFields);

        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => $structure,
        ];
    }

    /**
     * Dynamically infer relationship fields using actual model analysis when possible
     */
    private function inferRelationshipFields(string $property): array
    {
        $fields = [];

        // Try to analyze the actual related model if possible
        $relatedModelFields = $this->analyzeRelatedModel($property);
        if (! empty($relatedModelFields)) {
            return $relatedModelFields;
        }

        // Fallback to minimal generic structure that works for any domain
        // Only add 'name' field if the property name suggests it has one
        if ($this->propertyLikelyHasName($property)) {
            $fields['name'] = ['type' => 'string'];
        }

        // Add 'title' if property suggests it (common in content management)
        if ($this->propertyLikelyHasTitle($property)) {
            $fields['title'] = ['type' => 'string'];
        }

        return $fields;
    }

    /**
     * Try to analyze the actual related model to get real field structure
     */
    private function analyzeRelatedModel(string $property): array
    {
        // This could be enhanced to actually find and analyze the related model
        // For now, return empty to use generic fallback
        // In a full implementation, this would:
        // 1. Find the model class
        // 2. Check its fillable/visible fields
        // 3. Return actual field structure
        return [];
    }

    /**
     * Generic check if a property likely has a 'name' field
     */
    private function propertyLikelyHasName(string $property): bool
    {
        // Most entities have a name field, but not all
        // Exclude technical/system relationships that might not
        $excludePatterns = ['token', 'session', 'log', 'audit', 'permission'];

        foreach ($excludePatterns as $pattern) {
            if (str_contains($property, $pattern)) {
                return false;
            }
        }

        return true; // Most relationships have a name
    }

    /**
     * Generic check if a property likely has a 'title' field
     */
    private function propertyLikelyHasTitle(string $property): bool
    {
        // Only content-like entities typically have titles
        $titlePatterns = ['post', 'article', 'page', 'event', 'project', 'task'];

        foreach ($titlePatterns as $pattern) {
            if (str_contains($property, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
