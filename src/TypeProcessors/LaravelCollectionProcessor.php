<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

/**
 * Laravel Collection Processor
 * Handles Laravel Collection types and array transformations
 */
class LaravelCollectionProcessor implements TypeProcessor
{
    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown' &&
               (str_contains($property, 'collection') ||
                str_ends_with($property, 's') || // Plural might indicate collection
                isset($context['methodSource']) && str_contains($context['methodSource'], '->map('));
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        // Analyze if this is likely a collection based on method source
        if (isset($context['methodSource'])) {
            if (str_contains($context['methodSource'], '->map(') ||
                str_contains($context['methodSource'], '->toArray()')) {

                return [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'structure' => [
                            'id' => ['type' => 'string'],
                        ],
                    ],
                    'description' => 'Collection of items',
                ];
            }
        }

        // Check if property name suggests it's plural/collection
        if (str_ends_with($property, 's') && ! str_ends_with($property, 'ss')) {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Array of items',
            ];
        }

        return null;
    }

    public function getPriority(): int
    {
        return 80;
    }
}
