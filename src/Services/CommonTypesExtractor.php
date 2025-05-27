<?php

declare(strict_types=1);

namespace Codemystify\TypesGenerator\Services;

/**
 * Service for extracting types that should go in common.ts
 */
class CommonTypesExtractor
{
    public function __construct(
        private array $config = []
    ) {}

    /**
     * Extract types that should go in common.ts
     *
     * @return array<string, array{name: string, structure: array, usageCount: int, groups: array<string>}>
     */
    public function extractCommonTypes(TypeRegistry $registry): array
    {
        $threshold = $this->config['threshold'] ?? 2;
        $commonCandidates = $registry->getCommonTypes($threshold);
        $extractedTypes = [];

        foreach ($commonCandidates as $fingerprint => $typeInfo) {
            if ($this->shouldExtract($typeInfo)) {
                $preferredName = $this->selectPreferredTypeName($typeInfo['names']);

                $extractedTypes[$fingerprint] = [
                    'name' => $preferredName,
                    'structure' => $typeInfo['structure'],
                    'usageCount' => count($typeInfo['names']),
                    'groups' => $typeInfo['groups'],
                    'originalNames' => $typeInfo['names'],
                ];
            }
        }

        return $extractedTypes;
    }

    /**
     * Decide if a type should be extracted (frequency, size, etc.)
     */
    public function shouldExtract(array $typeInfo): bool
    {
        $excludePatterns = $this->getExcludePatterns();

        // Check if any type name matches exclude patterns
        foreach ($typeInfo['names'] as $typeName) {
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $typeName)) {
                    return false;
                }
            }
        }

        // Don't extract simple types (single field)
        if ($this->isSimpleType($typeInfo['structure'])) {
            return false;
        }

        // Don't extract if used in only one group (shouldn't happen with threshold=2)
        if (count($typeInfo['groups']) < 2) {
            return false;
        }

        // Extract if type is complex enough
        return $this->isComplexEnough($typeInfo['structure']);
    }

    /**
     * Determine best common file name for extracted types
     */
    public function determineCommonsFileName(): string
    {
        return $this->config['file_name'] ?? 'common';
    }

    /**
     * Select the most appropriate name from duplicate type names
     */
    private function selectPreferredTypeName(array $names): string
    {
        if (empty($names)) {
            return 'UnknownType';
        }

        // Prefer shorter, more generic names
        usort($names, function ($a, $b) {
            // Prefer names without suffixes like "Response", "Data", etc.
            $aScore = $this->getNameScore($a);
            $bScore = $this->getNameScore($b);

            if ($aScore !== $bScore) {
                return $aScore <=> $bScore;
            }

            // If scores are equal, prefer shorter names
            return strlen($a) <=> strlen($b);
        });

        return $names[0];
    }

    /**
     * Calculate a score for type name preference (lower is better)
     */
    private function getNameScore(string $name): int
    {
        $score = 0;

        // Penalize common suffixes
        $badSuffixes = ['Response', 'Data', 'Resource', 'DTO', 'Model'];
        foreach ($badSuffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $score += 10;
            }
        }

        // Prefer common base types using generic patterns
        $commonPatterns = ['User', 'Category', 'Tag', 'Address', 'Image', 'File', 'Media'];
        foreach ($commonPatterns as $pattern) {
            if (str_contains($name, $pattern)) {
                $score -= 20;
                break;
            }
        }

        // Penalize very long names
        if (strlen($name) > 20) {
            $score += 5;
        }

        return $score;
    }

    /**
     * Check if type structure is simple (few fields)
     */
    private function isSimpleType(array $structure): bool
    {
        // Consider types with only 1 field as simple
        // Types with 2+ fields could be worth extracting if used across groups
        return count($structure) <= 1;
    }

    /**
     * Check if type is complex enough to warrant extraction
     */
    private function isComplexEnough(array $structure): bool
    {
        // Must have at least 2 fields or contain nested objects
        if (count($structure) >= 2) {
            return true;
        }

        // Check for nested complexity
        foreach ($structure as $value) {
            if (is_array($value) && ! empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get patterns for types to exclude from extraction
     *
     * @return array<string>
     */
    private function getExcludePatterns(): array
    {
        $excludeString = $this->config['exclude_patterns'] ?? '';

        if (empty($excludeString)) {
            return [
                '/^.*Test.*$/i',
                '/^.*Mock.*$/i',
                '/^.*Temp.*$/i',
            ];
        }

        $patterns = array_map('trim', explode(',', $excludeString));

        return array_map(function ($pattern) {
            // Wrap in delimiters if not already wrapped
            if (! str_starts_with($pattern, '/')) {
                return '/'.preg_quote($pattern, '/').'/i';
            }

            return $pattern;
        }, $patterns);
    }
}
