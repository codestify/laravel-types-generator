<?php

namespace Codemystify\TypesGenerator\Exceptions;

class AnalysisException extends TypesGeneratorException
{
    public static function methodAnalysisFailed(string $className, string $methodName, string $reason = ''): self
    {
        return new self("Failed to analyze method {$className}::{$methodName}. {$reason}");
    }

    public static function invalidMethodStructure(string $className, string $methodName): self
    {
        return new self("Invalid method structure in {$className}::{$methodName}");
    }

    public static function unsupportedMethodType(string $className): self
    {
        return new self("Unsupported method type for class: {$className}");
    }
}
