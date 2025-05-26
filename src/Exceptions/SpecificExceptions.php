<?php

namespace Codemystify\TypesGenerator\Exceptions;

class SchemaAnalysisException extends TypesGeneratorException
{
    public static function migrationParsingFailed(string $filename, string $reason = ''): self
    {
        return new self("Failed to parse migration file {$filename}. {$reason}");
    }

    public static function invalidMigrationStructure(string $filename): self
    {
        return new self("Invalid migration structure in file: {$filename}");
    }
}
