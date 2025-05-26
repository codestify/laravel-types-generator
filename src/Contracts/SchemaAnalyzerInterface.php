<?php

namespace Codemystify\TypesGenerator\Contracts;

interface SchemaAnalyzerInterface
{
    /**
     * Analyze all database migrations
     *
     * @throws \Codemystify\TypesGenerator\Exceptions\SchemaAnalysisException
     */
    public function analyzeAllMigrations(): array;

    /**
     * Get schema information for a specific table
     */
    public function getTableSchema(string $tableName): ?array;

    /**
     * Clear any cached schema information
     */
    public function clearCache(): void;
}
