<?php

declare(strict_types=1);

namespace Codemystify\TypesGenerator\Services;

/**
 * Registry for tracking generated types and detecting duplicates
 */
class TypeRegistry
{
    /**
     * @var array<string, array{structure: array, group: string, source: string, fingerprint: string}>
     */
    private array $types = [];

    /**
     * @var array<string, array<string>>
     */
    private array $fingerprints = [];

    /**
     * Register a generated type
     */
    public function registerType(string $name, array $structure, string $group, string $source): void
    {
        $fingerprint = $this->generateFingerprint($structure);

        $this->types[$name] = [
            'structure' => $structure,
            'group' => $group,
            'source' => $source,
            'fingerprint' => $fingerprint,
        ];

        if (! isset($this->fingerprints[$fingerprint])) {
            $this->fingerprints[$fingerprint] = [];
        }

        $this->fingerprints[$fingerprint][] = $name;
    }

    /**
     * Find types with identical structures
     *
     * @return array<string, array<string>>
     */
    public function findDuplicates(): array
    {
        $duplicates = [];

        foreach ($this->fingerprints as $fingerprint => $typeNames) {
            if (count($typeNames) > 1) {
                $duplicates[$fingerprint] = $typeNames;
            }
        }

        return $duplicates;
    }

    /**
     * Get types used across multiple groups (candidates for commons)
     *
     * @return array<string, array{names: array<string>, groups: array<string>, structure: array}>
     */
    public function getCommonTypes(int $threshold = 2): array
    {
        $commonTypes = [];
        $duplicates = $this->findDuplicates();

        foreach ($duplicates as $fingerprint => $typeNames) {
            $groups = [];
            $structure = null;

            foreach ($typeNames as $typeName) {
                $typeInfo = $this->types[$typeName];
                $groups[] = $typeInfo['group'];

                if ($structure === null) {
                    $structure = $typeInfo['structure'];
                }
            }

            $uniqueGroups = array_unique($groups);

            if (count($uniqueGroups) >= $threshold) {
                $commonTypes[$fingerprint] = [
                    'names' => $typeNames,
                    'groups' => $uniqueGroups,
                    'structure' => $structure,
                ];
            }
        }

        return $commonTypes;
    }

    /**
     * Get all registered types
     *
     * @return array<string, array{structure: array, group: string, source: string, fingerprint: string}>
     */
    public function getAllTypes(): array
    {
        return $this->types;
    }

    /**
     * Get type information by name
     *
     * @return array{structure: array, group: string, source: string, fingerprint: string}|null
     */
    public function getType(string $name): ?array
    {
        return $this->types[$name] ?? null;
    }

    /**
     * Check if a type is registered
     */
    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * Get types by group
     *
     * @return array<string, array{structure: array, group: string, source: string, fingerprint: string}>
     */
    public function getTypesByGroup(string $group): array
    {
        return array_filter($this->types, fn ($type) => $type['group'] === $group);
    }

    /**
     * Clear all registered types
     */
    public function clear(): void
    {
        $this->types = [];
        $this->fingerprints = [];
    }

    /**
     * Generate unique fingerprint for type structure
     */
    private function generateFingerprint(array $structure): string
    {
        // Normalize structure for consistent fingerprinting
        $normalized = $this->normalizeStructure($structure);

        // Sort keys recursively to ensure consistent ordering
        ksort($normalized);

        return hash('sha256', serialize($normalized));
    }

    /**
     * Normalize structure for fingerprint generation
     */
    private function normalizeStructure(array $structure): array
    {
        $normalized = [];

        foreach ($structure as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $this->normalizeStructure($value);
            } else {
                // Normalize basic types
                $normalized[$key] = $this->normalizeType($value);
            }
        }

        return $normalized;
    }

    /**
     * Normalize type representation
     */
    private function normalizeType(mixed $type): string
    {
        if (is_string($type)) {
            // Remove whitespace and normalize common type variations
            $normalized = trim($type);
            $normalized = preg_replace('/\s+/', ' ', $normalized);
            $normalized = str_replace(['string|null', 'null|string'], 'string | null', $normalized);
            $normalized = str_replace(['number|null', 'null|number'], 'number | null', $normalized);

            return $normalized;
        }

        return (string) $type;
    }
}
