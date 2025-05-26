<?php

namespace Codemystify\TypesGenerator\Services;

use Codemystify\TypesGenerator\Attributes\GenerateTypes;
use Codemystify\TypesGenerator\Utils\PathResolver;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RegexIterator;

class TypeGeneratorService
{
    private SimpleReflectionAnalyzer $reflectionAnalyzer;

    private MigrationAnalyzer $migrationAnalyzer;

    private TypeScriptGenerator $typeScriptGenerator;

    private array $generatedTypes = [];

    private array $config;

    public function __construct(
        SimpleReflectionAnalyzer $reflectionAnalyzer,
        MigrationAnalyzer $migrationAnalyzer,
        TypeScriptGenerator $typeScriptGenerator
    ) {
        $this->reflectionAnalyzer = $reflectionAnalyzer;
        $this->migrationAnalyzer = $migrationAnalyzer;
        $this->typeScriptGenerator = $typeScriptGenerator;
        $this->config = config('types-generator');
    }

    public function generateTypes(array $options = []): array
    {
        $annotatedMethods = $this->discoverAnnotatedMethods($options);
        $schemaInfo = $this->migrationAnalyzer->analyzeAllMigrations();

        foreach ($annotatedMethods as $method) {
            $this->processAnnotatedMethod($method, $schemaInfo);
        }

        return $this->typeScriptGenerator->generateFiles($this->generatedTypes);
    }

    private function discoverAnnotatedMethods(array $options = []): array
    {
        $methods = [];

        // Scan Resources
        $methods = array_merge($methods, $this->scanDirectory(
            $this->config['sources']['resources_path'],
            $this->config['namespaces']['resources']
        ));

        // Scan Controllers
        $methods = array_merge($methods, $this->scanDirectory(
            $this->config['sources']['controllers_path'],
            $this->config['namespaces']['controllers']
        ));

        return $this->filterMethodsByOptions($methods, $options);
    }

    private function scanDirectory(string $path, string $namespace): array
    {
        $resolvedPath = PathResolver::resolve($path);

        if (! is_dir($resolvedPath)) {
            return [];
        }

        $methods = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedPath)
        );
        $phpFiles = new RegexIterator($iterator, '/\.php$/');

        foreach ($phpFiles as $file) {
            $className = $this->getClassNameFromFile($file->getPathname(), $namespace, $resolvedPath);

            if (! $className || ! class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $methods = array_merge($methods, $this->getAnnotatedMethods($reflection));
        }

        return $methods;
    }

    private function getAnnotatedMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(GenerateTypes::class);
            if (! empty($attributes)) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    private function processAnnotatedMethod(ReflectionMethod $method, array $schemaInfo): void
    {
        $attributes = $method->getAttributes(GenerateTypes::class);

        foreach ($attributes as $attribute) {
            $config = $attribute->newInstance();

            $typeStructure = $this->reflectionAnalyzer->analyzeMethod($method, $schemaInfo);

            $this->generatedTypes[$config->name] = [
                'config' => $config,
                'structure' => $typeStructure,
                'source' => $method->getDeclaringClass()->getName().'::'.$method->getName(),
                'group' => $config->group ?? 'default',
            ];
        }
    }

    private function getClassNameFromFile(string $filePath, string $namespace, string $basePath): ?string
    {
        $relativePath = str_replace($basePath, '', $filePath);
        $relativePath = trim($relativePath, '/\\');
        $relativePath = str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);

        return $namespace.'\\'.$relativePath;
    }

    private function filterMethodsByOptions(array $methods, array $options): array
    {
        if (isset($options['group'])) {
            return array_filter($methods, function ($method) use ($options) {
                $attributes = $method->getAttributes(GenerateTypes::class);
                foreach ($attributes as $attribute) {
                    $config = $attribute->newInstance();
                    if ($config->group === $options['group']) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $methods;
    }
}
