<?php

declare(strict_types=1);

namespace Codemystify\TypesGenerator\Services;

/**
 * Service for rewriting type references to use common imports
 */
class TypeReferenceRewriter
{
    public function __construct(
        private array $config = []
    ) {}

    /**
     * Update type definitions to reference common types
     *
     * @param  array  $types  Original types grouped by domain
     * @param  array  $commonTypes  Common types to be extracted
     *
     * @return array Updated types with references rewritten
     */
    public function rewriteReferences(array $types, array $commonTypes): array
    {
        $rewrittenTypes = [];
        $commonTypeNames = $this->extractCommonTypeNames($commonTypes);

        foreach ($types as $group => $groupTypes) {
            $rewrittenTypes[$group] = [];

            foreach ($groupTypes as $typeName => $typeDefinition) {
                // Check if this type name is in any of the original names of common types
                $shouldSkip = false;
                foreach ($commonTypes as $commonType) {
                    if (isset($commonType['originalNames']) && in_array($typeName, $commonType['originalNames'])) {
                        $shouldSkip = true;
                        break;
                    }
                }

                if ($shouldSkip) {
                    // This type will be moved to common.ts, skip it
                    continue;
                }

                // Rewrite the type definition to reference common types
                $rewrittenDefinition = $this->rewriteTypeDefinition(
                    $typeDefinition,
                    $commonTypeNames
                );

                $rewrittenTypes[$group][$typeName] = $rewrittenDefinition;
            }
        }

        return $rewrittenTypes;
    }

    /**
     * Generate import statements for common types
     */
    public function generateImportStatements(array $commonTypes, string $fromFile): string
    {
        if (empty($commonTypes)) {
            return '';
        }

        $commonFileName = $this->config['file_name'] ?? 'common';
        $importStyle = $this->config['import_style'] ?? 'relative';

        $importPath = $this->generateImportPath($commonFileName, $fromFile, $importStyle);
        $typeNames = [];

        // Extract type names correctly from the commonTypes structure
        foreach ($commonTypes as $commonType) {
            if (isset($commonType['name'])) {
                $typeNames[] = $commonType['name'];
            }
        }

        sort($typeNames); // Alphabetical order for consistency

        if (empty($typeNames)) {
            return '';
        }

        $importList = implode(', ', $typeNames);

        return "import type { {$importList} } from '{$importPath}';";
    }

    /**
     * Convert type reference to import
     *
     * @return array{shouldImport: bool, importName: string|null}
     */
    public function convertToReference(string $typeName, array $commonTypes): array
    {
        foreach ($commonTypes as $commonType) {
            if (in_array($typeName, $commonType['originalNames'] ?? [])) {
                return [
                    'shouldImport' => true,
                    'importName' => $commonType['name'],
                ];
            }
        }

        return [
            'shouldImport' => false,
            'importName' => null,
        ];
    }

    /**
     * Get list of types that need imports for a specific group
     *
     * @return array<string>
     */
    public function getRequiredImports(string $group, array $types, array $commonTypes): array
    {
        $requiredImports = [];
        $groupTypes = $types[$group] ?? [];

        foreach ($groupTypes as $typeName => $typeDefinition) {
            $imports = $this->extractTypeReferences($typeDefinition, $commonTypes);
            $requiredImports = array_merge($requiredImports, $imports);
        }

        return array_unique($requiredImports);
    }

    /**
     * Extract common type names from common types array
     *
     * @return array<string>
     */
    private function extractCommonTypeNames(array $commonTypes): array
    {
        $names = [];

        foreach ($commonTypes as $commonType) {
            if (isset($commonType['originalNames'])) {
                $names = array_merge($names, $commonType['originalNames']);
            }
        }

        return array_unique($names);
    }

    /**
     * Rewrite a single type definition to use common type references
     */
    private function rewriteTypeDefinition(array $typeDefinition, array $commonTypeNames): array
    {
        $rewritten = [];

        foreach ($typeDefinition as $fieldName => $fieldType) {
            if (is_array($fieldType)) {
                // Recursively handle nested types
                $rewritten[$fieldName] = $this->rewriteTypeDefinition($fieldType, $commonTypeNames);
            } elseif (is_string($fieldType)) {
                $rewritten[$fieldName] = $this->rewriteFieldType($fieldType, $commonTypeNames);
            } else {
                $rewritten[$fieldName] = $fieldType;
            }
        }

        return $rewritten;
    }

    /**
     * Rewrite a field type string to use common type references
     */
    private function rewriteFieldType(string $fieldType, array $commonTypeNames): string
    {
        // Handle union types (e.g., "User | null")
        if (str_contains($fieldType, '|')) {
            $parts = array_map('trim', explode('|', $fieldType));
            $rewrittenParts = [];

            foreach ($parts as $part) {
                if (in_array($part, $commonTypeNames)) {
                    $rewrittenParts[] = $part; // Keep as reference
                } else {
                    $rewrittenParts[] = $part;
                }
            }

            return implode(' | ', $rewrittenParts);
        }

        // Handle array types (e.g., "User[]")
        if (str_ends_with($fieldType, '[]')) {
            $baseType = substr($fieldType, 0, -2);
            if (in_array($baseType, $commonTypeNames)) {
                return $baseType.'[]'; // Keep as reference
            }
        }

        // Handle generic types (e.g., "Array<User>")
        if (preg_match('/^([^<]+)<([^>]+)>$/', $fieldType, $matches)) {
            $container = $matches[1];
            $innerType = $matches[2];

            if (in_array($innerType, $commonTypeNames)) {
                return $container.'<'.$innerType.'>';
            }
        }

        // Simple type reference
        if (in_array($fieldType, $commonTypeNames)) {
            return $fieldType; // Keep as reference
        }

        return $fieldType;
    }

    /**
     * Extract type references from a type definition
     *
     * @return array<string>
     */
    private function extractTypeReferences(array $typeDefinition, array $commonTypes): array
    {
        $references = [];
        $commonTypeNames = $this->extractCommonTypeNames($commonTypes);

        foreach ($typeDefinition as $fieldType) {
            if (is_array($fieldType)) {
                $nestedRefs = $this->extractTypeReferences($fieldType, $commonTypes);
                $references = array_merge($references, $nestedRefs);
            } elseif (is_string($fieldType)) {
                $refs = $this->extractReferencesFromFieldType($fieldType, $commonTypeNames);
                $references = array_merge($references, $refs);
            }
        }

        return array_unique($references);
    }

    /**
     * Extract references from a field type string
     *
     * @return array<string>
     */
    private function extractReferencesFromFieldType(string $fieldType, array $commonTypeNames): array
    {
        $references = [];

        // Extract all potential type references using regex
        preg_match_all('/\b([A-Z][a-zA-Z0-9]*)\b/', $fieldType, $matches);

        foreach ($matches[1] as $typeName) {
            if (in_array($typeName, $commonTypeNames)) {
                $references[] = $typeName;
            }
        }

        return $references;
    }

    /**
     * Generate import path based on import style
     */
    private function generateImportPath(string $commonFileName, string $fromFile, string $importStyle): string
    {
        if ($importStyle === 'absolute') {
            return './'.$commonFileName;
        }

        // Relative import (default)
        return './'.$commonFileName;
    }
}
