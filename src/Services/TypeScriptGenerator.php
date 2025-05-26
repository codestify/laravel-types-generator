<?php

namespace Codemystify\TypesGenerator\Services;

use Codemystify\TypesGenerator\Contracts\TypeScriptGeneratorInterface;
use Codemystify\TypesGenerator\Exceptions\GenerationException;
use Codemystify\TypesGenerator\Utils\PathResolver;
use Illuminate\Support\Facades\File;

class TypeScriptGenerator implements TypeScriptGeneratorInterface
{
    private array $config;

    private array $generatedInterfaces = [];

    private array $generatedEnums = [];

    public function __construct()
    {
        $this->config = config('types-generator');
    }

    public function generateFiles(array $types): array
    {
        $this->validateConfig();
        $this->ensureOutputDirectory();

        $results = [];
        $groupedTypes = $this->groupTypesByGroup($types);

        foreach ($groupedTypes as $group => $groupTypes) {
            $this->extractNestedTypes($groupTypes);
            $result = $this->generateGroupFile($group, $groupTypes);
            $results[] = $result;
        }

        if ($this->config['output']['index_file']) {
            $this->generateIndexFile($groupedTypes);
        }

        return $results;
    }

    public function generateTypeDefinition(string $typeName, array $typeData): string
    {
        $structure = $typeData['structure'] ?? [];

        // Handle null or invalid structures gracefully
        if (! is_array($structure)) {
            $structure = [];
        }

        if (! $this->validateStructure($structure)) {
            throw GenerationException::invalidTypeStructure($typeName);
        }

        $comment = $this->generateTypeComment($typeData['config'], $typeData['source']);
        $interface = $this->generateInterface($typeName, $structure);

        return $comment.$interface;
    }

    public function validateStructure(array $structure): bool
    {
        if ($this->config['validation']['validate_structures'] ?? true) {
            return $this->performStructureValidation($structure);
        }

        return true;
    }

    private function validateConfig(): void
    {
        if (! isset($this->config['output']['path'])) {
            throw GenerationException::invalidConfiguration('Output path not configured');
        }
    }

    private function performStructureValidation(array $structure): bool
    {
        foreach ($structure as $key => $field) {
            if (! is_string($key)) {
                return false;
            }
            if (! is_array($field) || ! isset($field['type'])) {
                return false;
            }
        }

        return true;
    }

    private function generateGroupFile(string $group, array $types): array
    {
        $filename = str_replace('{group}', $group, $this->config['output']['filename_pattern']);
        $outputPath = PathResolver::resolve($this->config['output']['path']);
        $filepath = $outputPath.'/'.$filename;

        $content = $this->generateTypeScriptContent($types);

        $this->backupIfExists($filepath);
        File::put($filepath, $content);

        return [
            'name' => $group,
            'file' => $filename,
            'types_count' => count($types),
            'status' => true,
            'source' => 'generated',
        ];
    }

    private function generateTypeScriptContent(array $types): string
    {
        $content = $this->generateHeader();

        foreach ($types as $typeName => $typeData) {
            $content .= $this->generateTypeDefinition($typeName, $typeData);
            $content .= "\n\n";
        }

        $content .= $this->generateFooter();

        return $content;
    }

    private function generateHeader(): string
    {
        $timestamp = now()->toISOString();

        return "/**\n".
            " * Auto-generated TypeScript types\n".
            " * Generated at: {$timestamp}\n".
            " * DO NOT EDIT MANUALLY - This file is auto-generated\n".
            " */\n\n";
    }

    private function generateTypeComment($config, string $source): string
    {
        if (! $this->config['generation']['include_comments']) {
            return '';
        }

        $comment = "/**\n";

        if (isset($config->description) && $config->description) {
            $comment .= " * {$config->description}\n";
        }

        $comment .= " * @source {$source}\n";

        if (isset($config->group) && $config->group) {
            $comment .= " * @group {$config->group}\n";
        }

        $comment .= " */\n";

        return $comment;
    }

    private function generateInterface(string $name, array $structure): string
    {
        $readonly = $this->config['generation']['include_readonly'] ? 'readonly ' : '';

        $interface = "export interface {$name} {\n";

        foreach ($structure as $key => $value) {
            $interface .= $this->generateProperty($key, $value, $readonly);
        }

        $interface .= '}';

        return $interface;
    }

    private function generateProperty(string $key, array $value, string $readonly): string
    {
        $optional = $this->isOptional($value) ? '?' : '';
        $type = $this->generatePropertyType($value);

        return "  {$readonly}{$key}{$optional}: {$type};\n";
    }

    private function generatePropertyType(array $value): string
    {
        $type = $value['type'] ?? 'unknown';

        // Handle type references first
        if (isset($value['reference'])) {
            return $value['reference'];
        }

        return match ($type) {
            'string' => $this->handleStringType($value),
            'number' => 'number',
            'boolean' => 'boolean',
            'array' => $this->handleArrayType($value),
            'object' => $this->handleObjectType($value),
            'enum' => $this->handleEnumType($value),
            'json' => 'Record<string, any>',
            'date' => 'string',
            'datetime' => 'string',
            'timestamps' => 'string',
            'null' => 'null',
            'reference' => $value['reference'] ?? 'unknown',
            default => 'unknown'
        };
    }

    private function handleStringType(array $value): string
    {
        if (isset($value['format'])) {
            return match ($value['format']) {
                'date' => 'string', // Could be Date if preferred
                'hijri' => 'string',
                'time' => 'string',
                default => 'string'
            };
        }

        return 'string';
    }

    private function handleArrayType(array $value): string
    {
        if (isset($value['items'])) {
            $itemType = $this->generatePropertyType($value['items']);

            return "{$itemType}[]";
        }

        return 'any[]';
    }

    private function handleObjectType(array $value): string
    {
        if (isset($value['structure']) && is_array($value['structure'])) {
            return $this->generateInlineInterface($value['structure'], 2);
        }

        return 'Record<string, any>';
    }

    private function generateInlineInterface(array $structure, int $indentLevel = 1): string
    {
        $readonly = $this->config['generation']['include_readonly'] ? 'readonly ' : '';
        $indent = str_repeat('  ', $indentLevel);

        $interface = "{\n";

        foreach ($structure as $key => $value) {
            $optional = $this->isOptional($value) ? '?' : '';
            $type = $this->generatePropertyType($value);
            $interface .= "{$indent}{$readonly}{$key}{$optional}: {$type};\n";
        }

        $interface .= str_repeat('  ', $indentLevel - 1).'}';

        return $interface;
    }

    private function handleEnumType(array $value): string
    {
        if (isset($value['values'])) {
            $values = array_map(fn ($v) => is_string($v) ? "'{$v}'" : $v, $value['values']);

            return implode(' | ', $values);
        }

        return 'string';
    }

    private function isOptional(array $value): bool
    {
        return ($value['nullable'] ?? false) ||
            isset($value['default']) ||
            ! $this->config['generation']['strict_types'];
    }

    private function groupTypesByGroup(array $types): array
    {
        $grouped = [];

        foreach ($types as $typeName => $typeData) {
            $group = $typeData['group'] ?? 'default';
            $grouped[$group][$typeName] = $typeData;
        }

        return $grouped;
    }

    private function generateIndexFile(array $groupedTypes): void
    {
        $outputPath = PathResolver::resolve($this->config['output']['path']);
        $indexPath = $outputPath.'/index.ts';

        $content = $this->generateHeader();
        $content .= "// Re-export all generated types\n\n";

        foreach (array_keys($groupedTypes) as $group) {
            $filename = str_replace(['{group}', '.ts'], [$group, ''], $this->config['output']['filename_pattern']);
            $content .= "export * from './{$filename}';\n";
        }

        File::put($indexPath, $content);
    }

    private function generateFooter(): string
    {
        return "\n// End of auto-generated types\n";
    }

    private function ensureOutputDirectory(): void
    {
        $path = PathResolver::resolve($this->config['output']['path']);

        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    private function backupIfExists(string $filepath): void
    {
        if (! $this->config['output']['backup_old_files'] || ! File::exists($filepath)) {
            return;
        }

        $backupPath = $filepath.'.backup.'.time();
        File::copy($filepath, $backupPath);
    }

    private function extractNestedTypes(array &$types): void
    {
        foreach ($types as $typeName => &$typeData) {
            if (isset($typeData['structure']) && is_array($typeData['structure'])) {
                $this->extractNestedTypesFromStructure($typeData['structure'], $typeName, $types);
            }
        }
    }

    private function extractNestedTypesFromStructure(array &$structure, string $parentName, array &$types): void
    {
        foreach ($structure as $key => &$field) {
            if (is_array($field) && isset($field['type']) && $field['type'] === 'object' && isset($field['structure']) && is_array($field['structure'])) {
                // Create a new interface for this nested object
                $nestedTypeName = $this->generateNestedTypeName($parentName, $key);

                // Add the nested type to our types collection
                $types[$nestedTypeName] = [
                    'config' => (object) [
                        'name' => $nestedTypeName,
                        'group' => $types[$parentName]['config']->group ?? 'default',
                        'description' => ucfirst($key).' data structure',
                    ],
                    'structure' => $field['structure'],
                    'source' => 'extracted_nested_type',
                ];

                // Replace the inline structure with a reference
                $field = [
                    'type' => 'reference',
                    'reference' => $nestedTypeName,
                ];

                // Recursively check for deeper nesting
                $this->extractNestedTypesFromStructure($types[$nestedTypeName]['structure'], $nestedTypeName, $types);
            }

            if ($field['type'] === 'array' && isset($field['items']['structure'])) {
                // Handle array of objects
                $nestedTypeName = $this->generateNestedTypeName($parentName, $key, true);

                $types[$nestedTypeName] = [
                    'config' => (object) [
                        'name' => $nestedTypeName,
                        'group' => $types[$parentName]['config']->group ?? 'default',
                        'description' => ucfirst(rtrim($key, 's')).' item data structure',
                    ],
                    'structure' => $field['items']['structure'],
                    'source' => 'extracted_nested_type',
                ];

                $field['items'] = [
                    'type' => 'reference',
                    'reference' => $nestedTypeName,
                ];

                // Recursively check for deeper nesting
                $this->extractNestedTypesFromStructure($types[$nestedTypeName]['structure'], $nestedTypeName, $types);
            }
        }
    }

    private function generateNestedTypeName(string $parentName, string $fieldName, bool $isArrayItem = false): string
    {
        $fieldName = ucfirst($this->camelCase($fieldName));

        if ($isArrayItem) {
            // For arrays, create singular name (e.g., recentActivity -> RecentActivity)
            $fieldName = rtrim($fieldName, 's');
        }

        return $fieldName.'Type';
    }

    private function camelCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
}
