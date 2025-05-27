<?php

namespace Codemystify\TypesGenerator\Services;

class FileTypeDetector
{
    private string $basePath;

    private array $typeCategories = [
        'resource',
        'controller',
        'model',
        'request',
        'response',
        'job',
        'event',
        'listener',
        'notification',
        'mail',
        'service',
        'repository',
    ];

    public function __construct()
    {
        $this->basePath = config('types-generator.output.base_path', 'resources/js/types');
    }

    public function detectFileType(string $filePath, string $className): string
    {
        // Dynamic file type detection based on path patterns
        $normalizedPath = str_replace('\\', '/', $filePath);

        // Extract type from path patterns dynamically
        if (preg_match('/\/(\w+)(?:\/[^\/]*)?\.php$/', $normalizedPath, $matches)) {
            $segment = strtolower($matches[1]);

            // Convert common Laravel directory names to type categories
            $typeMap = [
                'resources' => 'resource',
                'controllers' => 'controller',
                'models' => 'model',
                'requests' => 'request',
                'responses' => 'response',
                'jobs' => 'job',
                'events' => 'event',
                'listeners' => 'listener',
                'notifications' => 'notification',
                'mail' => 'mail',
                'services' => 'service',
                'repositories' => 'repository',
            ];

            if (isset($typeMap[$segment])) {
                return $typeMap[$segment];
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
        if (empty($fileType)) {
            return $this->basePath;
        }

        return match ($fileType) {
            'resource' => $this->basePath.'/resources/',
            'controller' => $this->basePath.'/controllers/',
            'model' => $this->basePath.'/models/',
            'unknown' => $this->basePath.'/generated/',
            default => $this->basePath.'/'
        };
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
