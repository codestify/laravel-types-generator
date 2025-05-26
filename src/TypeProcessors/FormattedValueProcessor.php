<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

/**
 * Formatted Value Processor
 * Handles formatted address fields and similar display values
 */
class FormattedValueProcessor implements TypeProcessor
{
    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown' &&
               (str_starts_with($property, 'formatted_') ||
                str_starts_with($property, 'display_') ||
                str_ends_with($property, '_display') ||
                str_ends_with($property, '_formatted'));
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        return [
            'type' => 'string',
            'nullable' => true,
            'description' => 'Formatted display value',
        ];
    }

    public function getPriority(): int
    {
        return 75;
    }
}
