<?php

namespace Codemystify\TypesGenerator\Exceptions;

use Exception;

class TypesGeneratorException extends Exception
{
    public static function invalidConfiguration(string $key, string $message = ''): self
    {
        return new self("Invalid configuration for '{$key}': {$message}");
    }

    public static function directoryNotFound(string $path): self
    {
        return new self("Directory not found: {$path}");
    }

    public static function fileNotReadable(string $path): self
    {
        return new self("File is not readable: {$path}");
    }

    public static function invalidPath(string $path): self
    {
        return new self("Invalid or unsafe path: {$path}");
    }
}
