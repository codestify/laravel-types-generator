<?php

namespace Codemystify\TypesGenerator\Services;

use Codemystify\TypesGenerator\Attributes\GenerateTypes;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class SimpleTypeGeneratorService
{
    public function __construct(
        private StructureParser $structureParser,
        private TypeScriptGenerator $typeScriptGenerator,
        private FileTypeDetector $fileTypeDetector
    ) {}

    public function generateTypes(array $options = []): array
    {
        $group = $options['group'] ?? null;
        $extractCommon = $options['extract_common'] ?? config('types-generator.aggregation.extract_common_types', true);
        $dryRun = $options['dry_run'] ?? false;

        // 1. Discover all #[GenerateTypes] attributes
        $attributes = $this->discoverAttributes($group);

        // 2. Build type registry from all discovered types
        $typeRegistry = $this->buildTypeRegistry($attributes);

        // 3. Generate TypeScript files
        $results = $this->generateTypeScriptFiles($attributes, $typeRegistry, $dryRun);

        // 4. Run intelligent aggregator if enabled
        if ($extractCommon && ! $dryRun && ! empty($results)) {
            $this->extractCommonTypes($results, $extractCommon);
        }

        return $results;
    }

    private function discoverAttributes(?string $group = null): array
    {
        $attributes = [];
        $sourcePaths = config('types-generator.sources', []);

        foreach ($sourcePaths as $relativePath) {
            $path = app_path(str_replace('app/', '', $relativePath));
            if (! is_dir($path)) {
                continue;
            }

            $files = File::allFiles($path);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $attributes = array_merge($attributes, $this->extractAttributesFromFile($file->getPathname(), $group));
            }
        }

        return $attributes;
    }

    private function extractAttributesFromFile(string $filePath, ?string $group): array
    {
        $attributes = [];

        try {
            $content = file_get_contents($filePath);

            // Extract namespace and class name
            preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches);
            preg_match('/class\s+(\w+)/', $content, $classMatches);

            if (! $namespaceMatches || ! $classMatches) {
                return [];
            }

            $className = $namespaceMatches[1].'\\'.$classMatches[1];

            if (! class_exists($className)) {
                return [];
            }

            $reflection = new ReflectionClass($className);
            $attributes = array_merge($attributes, $this->extractClassAttributes($reflection, $filePath, $group));
            $attributes = array_merge($attributes, $this->extractMethodAttributes($reflection, $filePath, $group));

        } catch (\Exception $e) {
            // Skip files that can't be processed
        }

        return $attributes;
    }

    private function extractClassAttributes(ReflectionClass $reflection, string $filePath, ?string $group): array
    {
        $attributes = [];

        foreach ($reflection->getAttributes(GenerateTypes::class) as $attribute) {
            $instance = $attribute->newInstance();

            if ($group && $instance->group !== $group) {
                continue;
            }

            $fileType = $instance->fileType ?? $this->fileTypeDetector->detectFileType($filePath, $reflection->getName());

            $attributes[] = [
                'attribute' => $instance,
                'class' => $reflection->getName(),
                'file_path' => $filePath,
                'file_type' => $fileType,
                'target' => 'class',
            ];
        }

        return $attributes;
    }

    private function extractMethodAttributes(ReflectionClass $reflection, string $filePath, ?string $group): array
    {
        $attributes = [];

        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(GenerateTypes::class) as $attribute) {
                $instance = $attribute->newInstance();

                if ($group && $instance->group !== $group) {
                    continue;
                }

                $fileType = $instance->fileType ?? $this->fileTypeDetector->detectFileType($filePath, $reflection->getName());

                $attributes[] = [
                    'attribute' => $instance,
                    'class' => $reflection->getName(),
                    'method' => $method->getName(),
                    'file_path' => $filePath,
                    'file_type' => $fileType,
                    'target' => 'method',
                ];
            }
        }

        return $attributes;
    }

    private function buildTypeRegistry(array $attributes): array
    {
        $registry = [];

        foreach ($attributes as $attributeData) {
            $attribute = $attributeData['attribute'];

            // Merge types from each attribute into global registry
            foreach ($attribute->types as $typeName => $typeStructure) {
                $registry[$typeName] = $typeStructure;
            }
        }

        return $registry;
    }

    private function generateTypeScriptFiles(array $attributes, array $typeRegistry, bool $dryRun): array
    {
        $results = [];
        $basePath = base_path();
        $outputPath = $basePath.'/'.$this->fileTypeDetector->getOutputPath().'/';

        foreach ($attributes as $attributeData) {
            $attribute = $attributeData['attribute'];

            // Check if structure is empty and provide helpful guidance
            if (empty($attribute->structure)) {
                if ($dryRun) {
                    echo "\n⚠️  Warning: {$attribute->name} has no structure defined.\n";
                    echo "   Add a structure array to your #[GenerateTypes] attribute like:\n";
                    echo "   #[GenerateTypes('{$attribute->name}', [\n";
                    echo "       'id' => 'string',\n";
                    echo "       'name' => 'string',\n";
                    echo "       // ... define your type structure\n";
                    echo "   ])]\n\n";
                }

                continue; // Skip types without structure
            }

            // Parse the structure
            $parsedStructure = $this->structureParser->parse($attribute->structure, $typeRegistry);

            // Generate TypeScript interface
            $interface = $this->typeScriptGenerator->generateInterface($attribute->name, $parsedStructure);

            // Generate any related types from the types registry
            $relatedTypes = [];
            foreach ($attribute->types as $typeName => $typeStructure) {
                $parsedTypeStructure = $this->structureParser->parse($typeStructure, $typeRegistry);
                $relatedTypes[] = $this->typeScriptGenerator->generateInterface($typeName, $parsedTypeStructure);
            }

            // Combine all content
            $content = $this->typeScriptGenerator->generateFileContent(array_merge([$interface], $relatedTypes));

            // Add header comment if enabled
            if (config('types-generator.files.add_header_comment', true)) {
                $headerComment = "// Auto-generated TypeScript types\n// Generated by Laravel Types Generator\n// Do not edit this file manually\n\n";
                $content = $headerComment.$content;
            }

            // All files go to the same directory (no type-based separation)
            $fileExtension = config('types-generator.files.extension', 'ts');
            $fileName = $this->sanitizeFileName($attribute->name).'.'.$fileExtension;
            $fullPath = $outputPath.$fileName;

            $results[] = [
                'name' => $attribute->name,
                'path' => $fullPath,
                'content' => $content,
                'file_type' => $attributeData['file_type'],
                'group' => $attribute->group,
            ];

            // Write file if not dry run
            if (! $dryRun) {
                if (! is_dir($outputPath)) {
                    mkdir($outputPath, 0755, true);
                }
                file_put_contents($fullPath, $content);
            }
        }

        return $results;
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

    private function extractCommonTypes(array $results, bool $extractCommon): void
    {
        if (empty($results) || ! $extractCommon) {
            return;
        }

        $aggregator = app(IntelligentTypeAggregator::class);
        $analysis = $aggregator->analyzeTypes($results);

        // Generate common types file if common types were found AND extraction is enabled
        if (! empty($analysis['common_types'])) {
            $commonTypesContent = $aggregator->generateCommonTypesFile();
            $fileExtension = config('types-generator.files.extension', 'ts');
            $commonFileName = config('types-generator.aggregation.commons_file_name', 'common').'.'.$fileExtension;
            $outputPath = base_path().'/'.$this->fileTypeDetector->getOutputPath().'/';
            $commonFilePath = $outputPath.$commonFileName;

            if (! is_dir($outputPath)) {
                mkdir($outputPath, 0755, true);
            }

            file_put_contents($commonFilePath, $commonTypesContent);

            // Update existing types to reference common types where possible
            $this->optimizeExistingTypes($results, $analysis['common_types'], $outputPath);
        }

        // Generate index file if enabled
        if (config('types-generator.generation.generate_index', true)) {
            $indexContent = $aggregator->generateIndexFile($results);
            $fileExtension = config('types-generator.files.extension', 'ts');
            $indexFileName = config('types-generator.aggregation.index_file_name', 'index').'.'.$fileExtension;
            $outputPath = base_path().'/'.$this->fileTypeDetector->getOutputPath().'/';
            $indexFilePath = $outputPath.$indexFileName;

            if (! is_dir($outputPath)) {
                mkdir($outputPath, 0755, true);
            }

            file_put_contents($indexFilePath, $indexContent);
        }
    }

    private function optimizeExistingTypes(array $results, array $commonTypes, string $outputPath): void
    {
        if (! config('types-generator.generation.handle_collisions', true)) {
            return;
        }

        // Create a mapping of all types and their source files
        $typeRegistry = $this->buildGlobalTypeRegistry($results);

        foreach ($results as $result) {
            $content = $result['content'];
            $currentFileName = $this->sanitizeFileName($result['name']);

            $optimizedContent = $this->generateCleanContent($content, $commonTypes, $typeRegistry, $currentFileName);

            // Only write if content actually changed
            if ($optimizedContent !== $content) {
                $fileName = $currentFileName.'.'.config('types-generator.files.extension', 'ts');
                $filePath = $outputPath.$fileName;
                file_put_contents($filePath, $optimizedContent);
            }
        }
    }

    private function buildGlobalTypeRegistry(array $results): array
    {
        $registry = [];

        foreach ($results as $result) {
            $fileName = $this->sanitizeFileName($result['name']);
            $content = $result['content'];

            // Extract all interface/type names from this file
            if (preg_match_all('/export\s+(?:interface|type)\s+(\w+)/m', $content, $matches)) {
                foreach ($matches[1] as $typeName) {
                    $registry[$typeName] = $fileName;
                }
            }
        }

        return $registry;
    }

    private function generateCleanContent(string $content, array $commonTypes, array $typeRegistry, string $currentFileName): string
    {
        $lines = [];
        $neededImports = [];
        $commonFileName = config('types-generator.aggregation.commons_file_name', 'common');

        // Find all type references in the content
        if (preg_match_all('/:\s*(\w+)(?:\[\])?(?:\s*\|\s*null)?/m', $content, $matches)) {
            $referencedTypes = array_unique($matches[1]);

            foreach ($referencedTypes as $typeName) {
                // Skip primitive types
                if (in_array(strtolower($typeName), ['string', 'number', 'boolean', 'any', 'unknown', 'void', 'null'])) {
                    continue;
                }

                // Check if type is in common types
                if (isset($commonTypes[$typeName])) {
                    $neededImports[$commonFileName][] = $typeName;

                    continue;
                }

                // Check if type is external (not in current file)
                if (isset($typeRegistry[$typeName]) && $typeRegistry[$typeName] !== $currentFileName) {
                    $sourceFile = $typeRegistry[$typeName];
                    $neededImports[$sourceFile][] = $typeName;
                }
            }
        }

        // Generate imports
        foreach ($neededImports as $sourceFile => $types) {
            $uniqueTypes = array_unique($types);
            $importTypes = implode(', ', $uniqueTypes);
            $lines[] = "import type { {$importTypes} } from './{$sourceFile}';";
        }

        if (! empty($neededImports)) {
            $lines[] = '';
        }

        // Add header comment if enabled
        if (config('types-generator.files.add_header_comment', true)) {
            $lines[] = '// Auto-generated TypeScript types';
            $lines[] = '// Generated by Laravel Types Generator';
            $lines[] = '// Do not edit this file manually';
            $lines[] = '';
        }

        // Clean the content (remove existing imports and comments)
        $cleanContent = $this->cleanExistingImportsAndComments($content);
        $lines[] = $cleanContent;

        return implode("\n", $lines);
    }

    private function cleanExistingImportsAndComments(string $content): string
    {
        $lines = explode("\n", $content);
        $cleanLines = [];
        $skipNext = false;

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            // Skip import statements
            if (str_starts_with($trimmedLine, 'import ')) {
                continue;
            }

            // Skip header comments
            if (str_starts_with($trimmedLine, '// Auto-generated') ||
                str_starts_with($trimmedLine, '// Generated by') ||
                str_starts_with($trimmedLine, '// Do not edit')) {
                continue;
            }

            // Skip empty lines at the beginning
            if (empty($cleanLines) && empty($trimmedLine)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        return implode("\n", $cleanLines);
    }

    private function parseInterfacesFromContent(string $content): array
    {
        // Simple parser to extract interface definitions
        $interfaces = [];
        $lines = explode("\n", $content);
        $currentInterface = null;
        $currentContent = [];

        foreach ($lines as $line) {
            if (preg_match('/export interface (\w+)/', $line, $matches)) {
                if ($currentInterface) {
                    $interfaces[$currentInterface] = implode("\n", $currentContent);
                }
                $currentInterface = $matches[1];
                $currentContent = [$line];
            } elseif ($currentInterface) {
                $currentContent[] = $line;
                if (trim($line) === '}') {
                    $interfaces[$currentInterface] = implode("\n", $currentContent);
                    $currentInterface = null;
                    $currentContent = [];
                }
            }
        }

        return $interfaces;
    }
}
