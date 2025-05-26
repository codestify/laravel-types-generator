<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

use ReflectionClass;

/**
 * Laravel Enum Processor
 * Dynamically detects and handles enum properties
 */
class LaravelEnumProcessor implements TypeProcessor
{
    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown' &&
               isset($context['modelClass']) &&
               $this->propertyLooksLikeEnum($property);
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        // Try to find enum class from model casts
        $enumClass = $this->findEnumClassFromModel($property, $context['modelClass']);

        if ($enumClass && enum_exists($enumClass)) {
            return $this->analyzeEnumClass($enumClass);
        }

        // Fallback to generic enum structure
        return [
            'type' => 'string',
            'description' => 'Enum value (string-based)',
        ];
    }

    public function getPriority(): int
    {
        return 85;
    }

    private function propertyLooksLikeEnum(string $property): bool
    {
        $enumPatterns = ['status', 'type', 'state', 'visibility', 'priority', 'level'];

        foreach ($enumPatterns as $pattern) {
            if (str_contains($property, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function findEnumClassFromModel(string $property, string $modelClass): ?string
    {
        try {
            $reflection = new ReflectionClass($modelClass);

            // Look for casts method
            if ($reflection->hasMethod('casts')) {
                $instance = $reflection->newInstanceWithoutConstructor();
                $casts = $instance->casts();

                if (isset($casts[$property])) {
                    $castType = $casts[$property];
                    if (enum_exists($castType)) {
                        return $castType;
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore reflection errors
        }

        return null;
    }

    private function analyzeEnumClass(string $enumClass): array
    {
        try {
            $reflection = new \ReflectionEnum($enumClass);
            $cases = [];

            foreach ($reflection->getCases() as $case) {
                $cases[] = $case->getBackingValue() ?? $case->getName();
            }

            return [
                'type' => $reflection->getBackingType()?->getName() ?? 'string',
                'enum_values' => $cases,
                'description' => "Enum: {$enumClass}",
            ];
        } catch (\Exception $e) {
            return ['type' => 'string', 'description' => 'Enum value'];
        }
    }
}
