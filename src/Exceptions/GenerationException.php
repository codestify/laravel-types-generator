<?php

namespace Codemystify\TypesGenerator\Exceptions;

class GenerationException extends TypesGeneratorException
{
    public static function fileWriteFailed(string $path, string $reason = ''): self
    {
        return new self("Failed to write file {$path}. {$reason}");
    }

    public static function invalidTypeStructure(string $typeName): self
    {
        return new self("Invalid type structure for: {$typeName}");
    }
}
