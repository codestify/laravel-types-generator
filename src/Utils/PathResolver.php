<?php

namespace Codemystify\TypesGenerator\Utils;

class PathResolver
{
    private static ?string $projectRoot = null;

    /**
     * Resolve a path relative to project root
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

        // Otherwise, resolve relative to detected project root
        return self::getProjectRoot().'/'.ltrim($path, '/\\');
    }

    /**
     * Get project root directory
     */
    public static function getProjectRoot(): string
    {
        if (self::$projectRoot === null) {
            self::$projectRoot = self::detectProjectRoot();
        }

        return self::$projectRoot;
    }

    /**
     * Auto-detect project root directory
     */
    private static function detectProjectRoot(): string
    {
        // If Laravel function exists, use it
        if (function_exists('base_path')) {
            return base_path();
        }

        // Start from current directory and walk up
        $currentDir = __DIR__;
        $maxLevels = 10;

        for ($i = 0; $i < $maxLevels; $i++) {
            // Check for project indicators
            if (self::isProjectRoot($currentDir)) {
                return $currentDir;
            }

            $parent = dirname($currentDir);
            if ($parent === $currentDir) {
                break; // Reached root
            }
            $currentDir = $parent;
        }

        // Fallback: assume vendor package structure
        return dirname(__DIR__, 4);
    }

    /**
     * Check if directory contains project indicators
     */
    private static function isProjectRoot(string $dir): bool
    {
        $indicators = [
            'composer.json',
            'artisan',
            'package.json',
            '.git',
            'bootstrap/app.php',
        ];

        foreach ($indicators as $indicator) {
            if (file_exists($dir.'/'.$indicator)) {
                return true;
            }
        }

        return false;
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
            return self::getProjectRoot().'/'.ltrim($matches[1], '/\\');
        }

        // base_path() without parameters
        if ($path === 'base_path()') {
            return self::getProjectRoot();
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
}
