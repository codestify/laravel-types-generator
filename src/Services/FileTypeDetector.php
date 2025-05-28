<?php

namespace Codemystify\TypesGenerator\Services;

class FileTypeDetector
{
    private string $basePath;

    private array $typeCategories;

    private array $directoryMapping;

    public function __construct()
    {
        $this->basePath = config('types-generator.output.base_path', 'resources/js/types');
        $this->typeCategories = config('types-generator.file_types.type_categories', ['unknown']);
        $this->directoryMapping = config('types-generator.file_types.directory_mapping', []);
    }

    public function detectFileType(string $filePath, string $className): string
    {
        // Dynamic file type detection based on path patterns
        $normalizedPath = str_replace('\\', '/', $filePath);

        // Extract type from path patterns dynamically
        if (preg_match('/\/(\w+)(?:\/[^\/]*)?\.php$/', $normalizedPath, $matches)) {
            $segment = strtolower($matches[1]);

            // Use configurable directory mapping
            if (isset($this->directoryMapping[$segment])) {
                return $this->directoryMapping[$segment];
            }
        }

        // Fallback to class name analysis
        $classSegments = explode('\\', $className);
        if (count($classSegments) >= 2) {
            $type = strtolower($classSegments[count($classSegments) - 2]);

            return in_array($type, $this->typeCategories) ? $type : 'unknown';
        }

        return 'unknown';
    }

    public function getOutputPath(string $fileType = ''): string
    {
        // All files go to the same base directory regardless of type
        // This maintains the current behavior of not separating by file type
        return $this->basePath;
    }

    public function getTypeCategories(): array
    {
        return $this->typeCategories;
    }

    public function getSourcePaths(): array
    {
        return config('types-generator.sources', []);
    }
}
