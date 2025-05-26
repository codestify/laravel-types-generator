<?php

namespace Codemystify\TypesGenerator\Utils;

class PathResolver
{
    /**
     * Resolve a path relative to Laravel's base path
     */
    public static function resolve(string $path): string
    {
        // If it's already an absolute path, return as-is
        if (self::isAbsolute($path)) {
            return $path;
        }

        // If it starts with base_path pattern, resolve it
        if (str_starts_with($path, 'base_path(')) {
            return self::resolveBasePath($path);
        }

        // Otherwise, resolve relative to base path
        return base_path($path);
    }

    /**
     * Check if a path is absolute
     */
    public static function isAbsolute(string $path): bool
    {
        // Unix/Linux absolute paths start with /
        if (str_starts_with($path, '/')) {
            return true;
        }

        // Windows absolute paths (C:\, D:\, etc.)
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve base_path() function calls to actual paths
     */
    private static function resolveBasePath(string $path): string
    {
        // Extract the parameter from base_path('parameter')
        if (preg_match('/base_path\([\'"]([^\'"]*)[\'"]?\)/', $path, $matches)) {
            return base_path($matches[1]);
        }

        // base_path() without parameters
        if ($path === 'base_path()') {
            return base_path();
        }

        return $path;
    }

    /**
     * Normalize path separators for the current OS
     */
    public static function normalize(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Ensure a directory exists, creating it if necessary
     */
    public static function ensureDirectory(string $path): bool
    {
        $resolvedPath = self::resolve($path);

        if (! is_dir($resolvedPath)) {
            return mkdir($resolvedPath, 0755, true);
        }

        return true;
    }

    /**
     * Resolve environment variable patterns in paths
     */
    public static function resolveEnvVars(string $path): string
    {
        return preg_replace_callback('/\$\{([^}]+)\}/', function ($matches) {
            return env($matches[1], '');
        }, $path);
    }
}
