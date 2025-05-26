<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

/**
 * Type processor interface inspired by Spatie's typescript-transformer
 * Processes unknown types and resolves them to proper TypeScript definitions
 */
interface TypeProcessor
{
    /**
     * Process a type and return a resolved type structure or null if cannot process
     */
    public function process(string $property, array $currentType, array $context): ?array;

    /**
     * Check if this processor can handle the given type
     */
    public function canProcess(string $property, array $currentType, array $context): bool;

    /**
     * Get the priority of this processor (higher = runs first)
     */
    public function getPriority(): int;
}
