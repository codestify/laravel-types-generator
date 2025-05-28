<?php

namespace Codemystify\TypesGenerator\Services;

class IntelligentTypeAggregator
{
    private array $allTypes = [];

    private array $commonTypes = [];

    private array $typeStructures = [];

    private array $propertyAnalysis = [];

    private float $similarityThreshold;

    private float $propertySimilarityThreshold;

    private int $minOccurrences;

    private int $minCommonProperties;

    public function __construct()
    {
        $this->similarityThreshold = config('types-generator.aggregation.similarity_threshold', 0.75);
        $this->propertySimilarityThreshold = config('types-generator.aggregation.property_similarity_threshold', 0.6);
        $this->minOccurrences = config('types-generator.aggregation.minimum_occurrence', 2);
        $this->minCommonProperties = config('types-generator.aggregation.minimum_common_properties', 2);
    }

    public function analyzeTypes(array $allGeneratedTypes): array
    {
        $this->allTypes = $this->extractTypeStructures($allGeneratedTypes);
        $this->analyzePropertyPatterns();
        $this->findStructuralSimilarities();
        $this->extractCommonTypesByFrequency();
        $this->generateIntelligentTypeNames();

        return $this->generateOptimizedTypes();
    }

    private function extractTypeStructures(array $generatedTypes): array
    {
        $types = [];

        foreach ($generatedTypes as $result) {
            $content = $result['content'];
            $interfaces = $this->parseTypeScriptInterfaces($content);

            foreach ($interfaces as $interfaceName => $structure) {
                $types[$interfaceName] = [
                    'structure' => $structure,
                    'source' => $result['file_type'],
                    'group' => $result['group'],
                    'original_name' => $result['name'],
                    'content' => $content,
                    'file_path' => $result['path'] ?? '',
                ];
            }
        }

        return $types;
    }

    private function parseTypeScriptInterfaces(string $content): array
    {
        $interfaces = [];
        $lines = explode("\n", $content);
        $currentInterface = null;
        $currentStructure = [];
        $braceLevel = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Start of interface
            if (preg_match('/export\s+interface\s+(\w+)\s*\{/', $trimmed, $matches)) {
                $currentInterface = $matches[1];
                $currentStructure = [];
                $braceLevel = 1;

                continue;
            }

            if ($currentInterface) {
                $braceLevel += substr_count($trimmed, '{') - substr_count($trimmed, '}');

                if ($braceLevel === 0) {
                    $interfaces[$currentInterface] = $currentStructure;
                    $currentInterface = null;
                    $currentStructure = [];

                    continue;
                }

                // Property line
                if (preg_match('/(\w+)(\??)\s*:\s*(.+?);?\s*$/', $trimmed, $matches)) {
                    $propertyName = $matches[1];
                    $optional = ! empty($matches[2]);
                    $type = rtrim($matches[3], ';');

                    $currentStructure[$propertyName] = [
                        'type' => $type,
                        'optional' => $optional,
                        'nullable' => str_contains($type, ' | null'),
                        'isArray' => str_ends_with($type, '[]') || str_contains($type, 'Array<'),
                        'complexity' => $this->calculateTypeComplexity($type),
                    ];
                }
            }
        }

        return $interfaces;
    }

    private function calculateTypeComplexity(string $type): int
    {
        $complexity = 0;

        // Base complexity for union types
        $complexity += substr_count($type, ' | ');

        // Complexity for arrays
        $complexity += substr_count($type, '[]');
        $complexity += substr_count($type, 'Array<');

        // Complexity for generics
        $complexity += substr_count($type, '<');

        // Complexity for object types
        $complexity += substr_count($type, '{');

        return max(1, $complexity);
    }

    private function analyzePropertyPatterns(): void
    {
        $this->propertyAnalysis = [
            'frequency' => [],
            'type_patterns' => [],
            'semantic_groups' => [],
            'naming_patterns' => [],
        ];

        foreach ($this->allTypes as $typeName => $typeData) {
            foreach ($typeData['structure'] as $propertyName => $propertyDef) {
                $propertyKey = $propertyName.':'.$this->normalizeType($propertyDef['type']);

                // Track frequency
                $this->propertyAnalysis['frequency'][$propertyKey] =
                    ($this->propertyAnalysis['frequency'][$propertyKey] ?? 0) + 1;

                // Track type patterns
                $typePattern = $this->extractTypePattern($propertyDef['type']);
                $this->propertyAnalysis['type_patterns'][$typePattern][] = $propertyName;

                // Analyze semantic groups
                $semanticGroup = $this->analyzePropertySemantics($propertyName, $propertyDef['type']);
                $this->propertyAnalysis['semantic_groups'][$semanticGroup][] = $propertyName;

                // Track naming patterns
                $namingPattern = $this->extractNamingPattern($propertyName);
                $this->propertyAnalysis['naming_patterns'][$namingPattern][] = $propertyName;
            }
        }
    }

    private function normalizeType(string $type): string
    {
        // Remove specific identifiers and keep structural information
        $normalized = preg_replace('/\b[A-Z][a-zA-Z0-9]*(?=\[\]|\s|$)/', 'T', $type);
        $normalized = preg_replace('/\b\d+\b/', 'N', $normalized);

        return $normalized;
    }

    private function extractTypePattern(string $type): string
    {
        if (str_contains($type, '[]') || str_contains($type, 'Array<')) {
            return 'array';
        }
        if (str_contains($type, ' | ')) {
            return 'union';
        }
        if (str_contains($type, '{')) {
            return 'object';
        }
        if (preg_match('/^[A-Z]/', $type)) {
            return 'interface';
        }

        return 'primitive';
    }

    private function analyzePropertySemantics(string $propertyName, string $type): string
    {
        $name = strtolower($propertyName);

        // Time-related patterns
        if (preg_match('/(date|time|at|created|updated|published|expired)/', $name)) {
            return 'temporal';
        }

        // Identifier patterns
        if (preg_match('/(id|uuid|key|identifier)$/', $name)) {
            return 'identifier';
        }

        // Relationship patterns
        if (preg_match('/_id$|_ids$/', $name)) {
            return 'relationship';
        }

        // Status/flag patterns
        if (preg_match('/(is_|has_|can_|should_|active|enabled|visible)/', $name)) {
            return 'boolean_flag';
        }

        // Content patterns
        if (preg_match('/(title|name|description|content|text|body|message)/', $name)) {
            return 'content';
        }

        // Numeric patterns
        if (preg_match('/(count|total|amount|price|quantity|number|size|length)/', $name)) {
            return 'numeric';
        }

        // Contact patterns
        if (preg_match('/(email|phone|address|url|website|link)/', $name)) {
            return 'contact';
        }

        return 'general';
    }

    private function extractNamingPattern(string $propertyName): string
    {
        if (preg_match('/^[a-z]+_[a-z]+/', $propertyName)) {
            return 'snake_case';
        }
        if (preg_match('/^[a-z]+[A-Z]/', $propertyName)) {
            return 'camelCase';
        }
        if (preg_match('/^[A-Z]/', $propertyName)) {
            return 'PascalCase';
        }

        return 'simple';
    }

    private function findStructuralSimilarities(): void
    {
        $structureGroups = [];

        foreach ($this->allTypes as $typeName => $typeData) {
            $structure = $typeData['structure'];
            $structureHash = $this->generateStructureHash($structure);

            if (! isset($structureGroups[$structureHash])) {
                $structureGroups[$structureHash] = [];
            }

            $structureGroups[$structureHash][] = [
                'name' => $typeName,
                'data' => $typeData,
                'similarity_score' => $this->calculateStructureSimilarity($structure),
            ];
        }

        // Filter groups that meet similarity threshold
        foreach ($structureGroups as $hash => $group) {
            if (count($group) >= $this->minOccurrences) {
                $avgSimilarity = array_sum(array_column($group, 'similarity_score')) / count($group);
                if ($avgSimilarity >= $this->similarityThreshold) {
                    $this->typeStructures[$hash] = $group;
                }
            }
        }
    }

    private function generateStructureHash(array $structure): string
    {
        $components = [];

        foreach ($structure as $property => $definition) {
            $semantic = $this->analyzePropertySemantics($property, $definition['type']);
            $pattern = $this->extractTypePattern($definition['type']);
            $optional = $definition['optional'] ? 'opt' : 'req';

            $components[] = "{$semantic}:{$pattern}:{$optional}";
        }

        sort($components);

        return md5(implode('|', $components));
    }

    private function calculateStructureSimilarity(array $structure): float
    {
        $score = 0;
        $totalProperties = count($structure);

        if ($totalProperties === 0) {
            return 0;
        }

        foreach ($structure as $property => $definition) {
            $semantic = $this->analyzePropertySemantics($property, $definition['type']);
            $frequency = $this->propertyAnalysis['frequency'][$property.':'.$this->normalizeType($definition['type'])] ?? 0;

            // Higher score for frequently occurring semantic patterns
            $semanticScore = count($this->propertyAnalysis['semantic_groups'][$semantic] ?? []) / count($this->allTypes);
            $frequencyScore = min($frequency / count($this->allTypes), 1.0);

            $score += ($semanticScore + $frequencyScore) / 2;
        }

        return $score / $totalProperties;
    }

    private function extractCommonTypesByFrequency(): void
    {
        $commonPropertyGroups = [];

        // Group properties by semantic similarity
        foreach ($this->propertyAnalysis['semantic_groups'] as $semantic => $properties) {
            if (count($properties) >= $this->minOccurrences) {
                $commonPropertyGroups[$semantic] = $this->buildPropertyGroup($properties, $semantic);
            }
        }

        // Extract cross-cutting concerns
        $this->extractCrossCuttingConcerns($commonPropertyGroups);

        // Build final common types
        foreach ($commonPropertyGroups as $semantic => $propertyGroup) {
            if (count($propertyGroup) >= $this->minCommonProperties) {
                $this->commonTypes[$semantic] = $propertyGroup;
            }
        }
    }

    private function buildPropertyGroup(array $properties, string $semantic): array
    {
        $propertyGroup = [];
        $propertyFrequency = [];

        // Analyze each property in the semantic group
        foreach ($properties as $property) {
            foreach ($this->allTypes as $typeName => $typeData) {
                if (isset($typeData['structure'][$property])) {
                    $definition = $typeData['structure'][$property];
                    $propertyKey = $property.':'.$this->normalizeType($definition['type']);
                    $propertyFrequency[$propertyKey] = ($propertyFrequency[$propertyKey] ?? 0) + 1;

                    if (! isset($propertyGroup[$property]) ||
                        $propertyFrequency[$propertyKey] > ($propertyGroup[$property]['frequency'] ?? 0)) {
                        $propertyGroup[$property] = array_merge($definition, [
                            'frequency' => $propertyFrequency[$propertyKey],
                            'semantic' => $semantic,
                        ]);
                    }
                }
            }
        }

        // Filter by minimum frequency threshold
        return array_filter($propertyGroup, function ($prop) {
            return $prop['frequency'] >= $this->minOccurrences;
        });
    }

    private function extractCrossCuttingConcerns(array &$commonPropertyGroups): void
    {
        $crossCuttingPatterns = [];

        // Find properties that appear across multiple semantic groups
        foreach ($commonPropertyGroups as $semantic1 => $group1) {
            foreach ($commonPropertyGroups as $semantic2 => $group2) {
                if ($semantic1 >= $semantic2) {
                    continue;
                }

                $intersection = array_intersect_key($group1, $group2);
                if (count($intersection) >= $this->minCommonProperties) {
                    $crossCuttingPatterns["{$semantic1}_{$semantic2}"] = $intersection;
                }
            }
        }

        // Add significant cross-cutting patterns as common types
        foreach ($crossCuttingPatterns as $pattern => $properties) {
            $this->commonTypes["shared_{$pattern}"] = $properties;
        }
    }

    private function generateIntelligentTypeNames(): void
    {
        $namedCommonTypes = [];

        foreach ($this->commonTypes as $key => $properties) {
            $intelligentName = $this->generateContextualTypeName($key, $properties);
            $namedCommonTypes[$intelligentName] = $properties;
        }

        // Resolve naming conflicts
        $this->commonTypes = $this->resolveNamingConflicts($namedCommonTypes);
    }

    private function generateContextualTypeName(string $key, array $properties): string
    {
        $propertyNames = array_keys($properties);
        $propertyCount = count($propertyNames);

        // Analyze property patterns for intelligent naming
        $patterns = $this->analyzePropertyPatternsForNames($propertyNames);

        // Generate name based on dominant patterns
        if ($patterns['has_timestamps'] && $patterns['has_id']) {
            return 'BaseEntity';
        }

        if ($patterns['has_timestamps']) {
            return 'TimestampedEntity';
        }

        if ($patterns['has_id'] && $patterns['has_content']) {
            return 'ContentEntity';
        }

        if ($patterns['has_status_flags']) {
            return 'StatusEntity';
        }

        if ($patterns['has_user_relations']) {
            return 'UserRelatedEntity';
        }

        if ($patterns['has_metadata']) {
            return 'MetadataEntity';
        }

        // Fallback to semantic-based naming
        $semanticNames = [
            'temporal' => 'TimeAware',
            'identifier' => 'Identifiable',
            'relationship' => 'Relational',
            'boolean_flag' => 'Configurable',
            'content' => 'ContentAware',
            'numeric' => 'Quantifiable',
            'contact' => 'Contactable',
        ];

        if (isset($semanticNames[$key])) {
            return $semanticNames[$key];
        }

        // Generate descriptive name from property combination
        return $this->generateDescriptiveTypeName($propertyNames, $propertyCount);
    }

    private function analyzePropertyPatternsForNames(array $propertyNames): array
    {
        $patterns = [
            'has_timestamps' => false,
            'has_id' => false,
            'has_content' => false,
            'has_status_flags' => false,
            'has_user_relations' => false,
            'has_metadata' => false,
        ];

        foreach ($propertyNames as $property) {
            $lower = strtolower($property);

            if (preg_match('/(created_at|updated_at|deleted_at)/', $lower)) {
                $patterns['has_timestamps'] = true;
            }

            if (preg_match('/^id$|_id$|uuid/', $lower)) {
                $patterns['has_id'] = true;
            }

            if (preg_match('/(title|name|description|content)/', $lower)) {
                $patterns['has_content'] = true;
            }

            if (preg_match('/(status|active|enabled|visible|is_|has_)/', $lower)) {
                $patterns['has_status_flags'] = true;
            }

            if (preg_match('/(user_id|author_id|creator_id|owner_id)/', $lower)) {
                $patterns['has_user_relations'] = true;
            }

            if (preg_match('/(meta|settings|config|data|attributes)/', $lower)) {
                $patterns['has_metadata'] = true;
            }
        }

        return $patterns;
    }

    private function generateDescriptiveTypeName(array $propertyNames, int $count): string
    {
        if ($count <= 2) {
            $sortedNames = array_map('ucfirst', $propertyNames);
            sort($sortedNames);

            return implode('', $sortedNames).'Type';
        }

        // For larger sets, use abbreviations or key terms
        $keyTerms = [];
        foreach ($propertyNames as $property) {
            if (strlen($property) <= 4) {
                $keyTerms[] = ucfirst($property);
            } else {
                $keyTerms[] = ucfirst(substr($property, 0, 3));
            }
        }

        return implode('', array_slice($keyTerms, 0, 3)).'Entity';
    }

    private function resolveNamingConflicts(array $namedTypes): array
    {
        $resolved = [];
        $usedNames = [];

        foreach ($namedTypes as $name => $properties) {
            $finalName = $name;
            $suffix = 1;

            while (in_array($finalName, $usedNames)) {
                $finalName = $name.$suffix;
                $suffix++;
            }

            $usedNames[] = $finalName;
            $resolved[$finalName] = $properties;
        }

        return $resolved;
    }

    private function generateOptimizedTypes(): array
    {
        return [
            'common_types' => $this->commonTypes,
            'type_structures' => $this->typeStructures,
            'original_types' => $this->allTypes,
            'property_analysis' => $this->propertyAnalysis,
            'optimization_metrics' => $this->calculateOptimizationMetrics(),
        ];
    }

    private function calculateOptimizationMetrics(): array
    {
        $totalProperties = 0;
        $optimizedProperties = 0;

        foreach ($this->allTypes as $type) {
            $totalProperties += count($type['structure']);
        }

        foreach ($this->commonTypes as $commonType) {
            $optimizedProperties += count($commonType);
        }

        return [
            'total_types_analyzed' => count($this->allTypes),
            'common_types_extracted' => count($this->commonTypes),
            'structure_groups_found' => count($this->typeStructures),
            'total_properties' => $totalProperties,
            'optimized_properties' => $optimizedProperties,
            'optimization_ratio' => $totalProperties > 0 ? round($optimizedProperties / $totalProperties * 100, 2) : 0,
            'similarity_threshold_used' => $this->similarityThreshold,
            'min_occurrences_threshold' => $this->minOccurrences,
        ];
    }

    public function generateCommonTypesFile(): string
    {
        if (empty($this->commonTypes)) {
            return '';
        }

        $lines = [];
        $lines[] = '// Automatically generated common types';
        $lines[] = '// These types represent shared structures found across multiple interfaces';
        $lines[] = '// Generated using intelligent structure analysis and semantic grouping';
        $lines[] = '';

        foreach ($this->commonTypes as $typeName => $properties) {
            $lines[] = "export interface {$typeName} {";

            foreach ($properties as $propertyName => $definition) {
                $optional = $definition['optional'] ? '?' : '';
                $type = $definition['type'];
                $frequency = $definition['frequency'] ?? 0;

                $comment = $frequency > 1 ? " // Used in {$frequency} types" : '';
                $lines[] = "  {$propertyName}{$optional}: {$type};{$comment}";
            }

            $lines[] = '}';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function generateIndexFile(array $typeFiles): string
    {
        $lines = [];
        $lines[] = '// Automatically generated index file';
        $lines[] = '// Exports all types from the types directory';
        $lines[] = '// Generated with intelligent dependency resolution';
        $lines[] = '';

        $commonFile = config('types-generator.aggregation.commons_file_name', 'common');
        $extractCommonTypes = config('types-generator.aggregation.extract_common_types', true);

        // Export common types first to prevent circular dependencies - only if extraction is enabled
        if ($extractCommonTypes && ! empty($this->commonTypes)) {
            $lines[] = '// Common types (export first to prevent circular dependencies)';
            $lines[] = "export * from './{$commonFile}';";
            $lines[] = '';
        }

        // Group other exports by category or just export all files
        $categorizedFiles = $this->categorizeTypeFiles($typeFiles, $commonFile);

        // If no categorization or all files are "Other", just export all files simply
        $hasMultipleCategories = count(array_filter($categorizedFiles, fn ($files) => ! empty($files))) > 1;

        if (! $hasMultipleCategories) {
            // Simple export of all types without categorization
            $lines[] = '// All generated types';
            foreach ($typeFiles as $file) {
                $fileName = $this->extractFileNameFromResult($file);
                if ($fileName && $fileName !== $commonFile) {
                    $lines[] = "export * from './{$fileName}';";
                }
            }
        } else {
            // Categorized export
            foreach ($categorizedFiles as $category => $files) {
                if (! empty($files)) {
                    $lines[] = "// {$category}";
                    foreach ($files as $file) {
                        $fileName = $this->extractFileNameFromResult($file);
                        if ($fileName && $fileName !== $commonFile) {
                            $lines[] = "export * from './{$fileName}';";
                        }
                    }
                    $lines[] = '';
                }
            }
        }

        return implode("\n", $lines);
    }

    private function extractFileNameFromResult(array $file): ?string
    {
        // Try to extract filename from path first
        if (isset($file['path']) && ! empty($file['path'])) {
            return basename($file['path'], '.ts');
        }

        // Try to use the name directly and sanitize it
        if (isset($file['name']) && ! empty($file['name'])) {
            return $this->sanitizeFileName($file['name']);
        }

        return null;
    }

    private function sanitizeFileName(string $name): string
    {
        $namingPattern = config('types-generator.files.naming_pattern', 'kebab-case');

        // Remove any invalid file name characters
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);

        return match ($namingPattern) {
            'kebab-case' => strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $sanitized)),
            'snake_case' => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $sanitized)),
            'camelCase' => lcfirst($sanitized),
            'PascalCase' => ucfirst($sanitized),
            default => strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $sanitized)),
        };
    }

    private function categorizeTypeFiles(array $typeFiles, string $commonFile): array
    {
        // Get configurable categories from config
        $indexCategories = config('types-generator.file_types.index_categories', [
            'Other' => ['unknown'],
        ]);

        // Initialize categories array
        $categories = [];
        foreach (array_keys($indexCategories) as $categoryName) {
            $categories[$categoryName] = [];
        }

        foreach ($typeFiles as $file) {
            // Use the actual filename from the path if available, otherwise use name
            $actualFileName = isset($file['path'])
                ? basename($file['path'], '.ts')
                : ($file['name'] ?? 'unknown');

            if ($actualFileName === $commonFile) {
                continue;
            }

            $fileType = $file['file_type'] ?? 'unknown';

            // Find which category this file type belongs to
            $categoryFound = false;
            foreach ($indexCategories as $categoryName => $fileTypes) {
                if (in_array($fileType, $fileTypes)) {
                    $categories[$categoryName][] = $file;
                    $categoryFound = true;
                    break;
                }
            }

            // If no category found, put in "Other" category
            if (! $categoryFound && isset($categories['Other'])) {
                $categories['Other'][] = $file;
            }
        }

        return array_filter($categories); // Remove empty categories
    }
}
