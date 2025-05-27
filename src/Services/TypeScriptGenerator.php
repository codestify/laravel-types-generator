<?php

namespace Codemystify\TypesGenerator\Services;

class TypeScriptGenerator
{
    private array $commonTypes = [];

    private bool $exportInterfaces;

    private bool $includeComments;

    private bool $includeReadonly;

    private bool $strictTypes;

    public function __construct()
    {
        $this->exportInterfaces = config('types-generator.generation.export_interfaces', true);
        $this->includeComments = config('types-generator.generation.include_comments', true);
        $this->includeReadonly = config('types-generator.generation.include_readonly', false);
        $this->strictTypes = config('types-generator.generation.strict_types', true);
    }

    public function setCommonTypes(array $commonTypes): void
    {
        $this->commonTypes = $commonTypes;
    }

    public function generateInterface(string $name, array $structure, array $extensions = []): string
    {
        if (! $this->exportInterfaces) {
            // Generate as type alias instead of interface
            return $this->generateType($name, $structure);
        }

        $lines = [];

        // Handle type extensions
        $extendsClause = '';
        if (! empty($extensions)) {
            $extendsClause = ' extends '.implode(', ', $extensions);
        }

        $lines[] = "export interface {$name}{$extendsClause} {";

        foreach ($structure as $key => $definition) {
            $lines[] = '  '.$this->generateProperty($key, $definition);
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function generateType(string $name, array $structure, array $unionTypes = []): string
    {
        if (! empty($unionTypes)) {
            return "export type {$name} = ".implode(' | ', $unionTypes).';';
        }

        $properties = [];

        foreach ($structure as $key => $definition) {
            $properties[] = '  '.$this->generateProperty($key, $definition);
        }

        return "export type {$name} = {\n".implode("\n", $properties)."\n};";
    }

    private function generateProperty(string $key, array $definition): string
    {
        $readonly = $this->includeReadonly && ($definition['readonly'] ?? false) ? 'readonly ' : '';
        $optional = $definition['optional'] ? '?' : '';
        $type = $this->generateTypeString($definition);
        $comment = $this->includeComments ? $this->generatePropertyComment($definition) : '';

        return "{$readonly}{$key}{$optional}: {$type};{$comment}";
    }

    private function generateTypeString(array $definition): string
    {
        $type = $definition['type'];

        // Apply strict types mode
        if ($this->strictTypes && $type === 'any') {
            $type = 'unknown';
        }

        // Handle arrays
        if ($definition['isArray']) {
            if (str_contains($type, '[]')) {
                // Already has array notation
            } else {
                $type = "{$type}[]";
            }
        }

        // Handle nullable
        if ($definition['nullable'] && ! str_contains($type, ' | null')) {
            $type = "{$type} | null";
        }

        return $type;
    }

    private function generatePropertyComment(array $definition): string
    {
        $comments = [];

        if (isset($definition['frequency']) && $definition['frequency'] > 1) {
            $comments[] = "Used in {$definition['frequency']} types";
        }

        if (isset($definition['semantic'])) {
            $comments[] = "Semantic: {$definition['semantic']}";
        }

        if (isset($definition['complexity']) && $definition['complexity'] > 1) {
            $comments[] = 'Complex type';
        }

        return ! empty($comments) ? ' // '.implode(', ', $comments) : '';
    }

    public function generateFileContent(array $interfaces, array $dependencies = [], array $imports = []): string
    {
        $lines = [];

        // Add imports for common types
        if (! empty($imports)) {
            foreach ($imports as $import) {
                $lines[] = $import;
            }
            $lines[] = '';
        }

        // Add dependencies/imports if any
        foreach ($dependencies as $dependency) {
            $lines[] = $dependency;
        }

        if (! empty($dependencies)) {
            $lines[] = '';
        }

        // Add interfaces
        $lines = array_merge($lines, $interfaces);

        return implode("\n", $lines);
    }

    public function generateOptimizedInterface(string $name, array $structure, array $commonTypeUsage = []): string
    {
        // Check if this interface can extend common types
        $extensions = [];
        $remainingProperties = $structure;

        foreach ($commonTypeUsage as $commonTypeName => $commonProperties) {
            $canExtend = true;
            foreach ($commonProperties as $propName => $propDef) {
                if (! isset($structure[$propName]) ||
                    $structure[$propName]['type'] !== $propDef['type']) {
                    $canExtend = false;
                    break;
                }
            }

            if ($canExtend) {
                $extensions[] = $commonTypeName;
                // Remove properties that are inherited from common type
                foreach ($commonProperties as $propName => $propDef) {
                    unset($remainingProperties[$propName]);
                }
            }
        }

        return $this->generateInterface($name, $remainingProperties, $extensions);
    }

    public function generateImportStatement(string $fileName, array $typeNames): string
    {
        if (empty($typeNames)) {
            return '';
        }

        $types = implode(', ', $typeNames);

        return "import type { {$types} } from './{$fileName}';";
    }
}
