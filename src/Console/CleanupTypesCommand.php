<?php

namespace Codemystify\TypesGenerator\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupTypesCommand extends Command
{
    protected $signature = 'types:cleanup 
                           {--remove-attributes : Remove all #[GenerateTypes] attributes}
                           {--keep-types : Keep generated .ts files}
                           {--dry-run : Show what would be removed}';

    protected $description = 'Clean up types generator attributes and files for production';

    public function handle(): int
    {
        $removeAttributes = $this->option('remove-attributes');
        $keepTypes = $this->option('keep-types');
        $dryRun = $this->option('dry-run');

        $this->info('Starting cleanup process...');

        try {
            $results = [
                'attributes_removed' => 0,
                'files_removed' => 0,
                'files_kept' => 0,
            ];

            if ($removeAttributes) {
                $results['attributes_removed'] = $this->removeAttributes($dryRun);
            }

            if (! $keepTypes) {
                $removeResults = $this->removeGeneratedTypes($dryRun);
                $results['files_removed'] = $removeResults['removed'];
                $results['files_kept'] = $removeResults['kept'];
            }

            $this->displayCleanupResults($results, $dryRun);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Cleanup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function removeAttributes(bool $dryRun): int
    {
        $count = 0;
        $sourcePaths = config('types-generator.sources', []);

        foreach ($sourcePaths as $relativePath) {
            $path = $this->resolvePath($relativePath);
            if (! is_dir($path)) {
                continue;
            }

            $files = File::allFiles($path);
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if ($this->removeAttributesFromFile($file->getPathname(), $dryRun)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function removeAttributesFromFile(string $filePath, bool $dryRun): bool
    {
        $content = file_get_contents($filePath);
        $originalContent = $content;

        // Remove #[GenerateTypes] attributes and their parameters
        $pattern = '/#\[GenerateTypes\([^]]*\)\]\s*/';
        $content = preg_replace($pattern, '', $content);

        // Remove use statement if it exists and no other attributes are used
        if (! str_contains($content, 'GenerateTypes')) {
            $usePattern = '/use\s+[^;]*GenerateTypes[^;]*;\s*/';
            $content = preg_replace($usePattern, '', $content);
        }

        if ($content !== $originalContent) {
            if (! $dryRun) {
                file_put_contents($filePath, $content);
            }

            return true;
        }

        return false;
    }

    private function removeGeneratedTypes(bool $dryRun): array
    {
        $typesPath = base_path(config('types-generator.output.base_path', 'resources/js/types'));
        $removed = 0;
        $kept = 0;

        if (! is_dir($typesPath)) {
            return ['removed' => 0, 'kept' => 0];
        }

        $files = File::allFiles($typesPath);

        foreach ($files as $file) {
            $generatedExtension = config('types-generator.files.extension', 'ts');
            if ($file->getExtension() === $generatedExtension) {
                if (! $dryRun) {
                    unlink($file->getPathname());
                }
                $removed++;
            } else {
                $kept++;
            }
        }

        return ['removed' => $removed, 'kept' => $kept];
    }

    private function displayCleanupResults(array $results, bool $dryRun): void
    {
        $prefix = $dryRun ? 'Would remove' : 'Removed';

        $this->info('Cleanup complete:');
        $this->line("  {$prefix} {$results['attributes_removed']} files with attributes");
        $this->line("  {$prefix} {$results['files_removed']} TypeScript files");

        if ($results['files_kept'] > 0) {
            $this->line("  Kept {$results['files_kept']} non-TypeScript files");
        }
    }

    private function resolvePath(string $relativePath): string
    {
        // If it's already an absolute path, return as-is
        if (str_starts_with($relativePath, '/') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:\\\\/', $relativePath))) {
            return $relativePath;
        }

        // Try Laravel's app_path first if available
        if (function_exists('app_path')) {
            return app_path(str_replace('app/', '', $relativePath));
        }

        // Fallback to base path resolution
        if (function_exists('base_path')) {
            return base_path($relativePath);
        }

        // Final fallback - assume current working directory
        return getcwd().'/'.$relativePath;
    }
}
