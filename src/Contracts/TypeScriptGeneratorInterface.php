<?php

namespace Codemystify\TypesGenerator\Contracts;

interface TypeScriptGeneratorInterface
{
    /**
     * Generate TypeScript files from type definitions
     *
     * @throws \Codemystify\TypesGenerator\Exceptions\GenerationException
     */
    public function generateFiles(array $types): array;

    /**
     * Generate TypeScript content for a single type
     */
    public function generateTypeDefinition(string $typeName, array $typeData): string;

    /**
     * Validate type structure before generation
     */
    public function validateStructure(array $structure): bool;
}
