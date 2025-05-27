<?php

namespace Codemystify\TypesGenerator\Console;

use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;
use Illuminate\Console\Command;

class GenerateTypesCommand extends Command
{
    protected $signature = 'types:generate 
                           {--group= : Generate specific group only}
                           {--no-common : Disable intelligent common types extraction}
                           {--dry-run : Show what would be generated}
                           {--stats : Show detailed analysis statistics}';

    protected $description = 'Generate TypeScript types with intelligent analysis and common type extraction';

    public function __construct(
        private SimpleTypeGeneratorService $typeGeneratorService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $options = [
            'group' => $this->option('group'),
            'extract_common' => $this->option('no-common') ? false : config('types-generator.aggregation.extract_common_types', true),
            'dry_run' => $this->option('dry-run'),
        ];

        $this->info('🚀 Starting intelligent type generation...');

        try {
            $results = $this->typeGeneratorService->generateTypes($options);

            if ($options['dry_run']) {
                $this->info('Dry run - showing what would be generated:');
                $this->displayDryRunResults($results);
            } else {
                $this->displayResults($results);

                if ($options['extract_common']) {
                    $this->info("\n🧠 Intelligent analysis completed:");
                    $this->info('  ✓ Common types extracted and optimized');
                    $this->info('  ✓ Structural similarities analyzed');
                    $this->info('  ✓ Index file generated with smart exports');

                    if ($this->option('stats')) {
                        $this->displayAnalysisStats();
                    }
                } else {
                    $this->info("\n📁 Simple type generation completed:");
                    $this->info('  ✓ Individual types generated');
                    $this->info('  ✓ Index file generated with exports');
                }
            }

            $this->info("\n🎉 Type generation completed successfully!");
            $outputPath = config('types-generator.output.base_path');
            $this->info("📁 All types available in: {$outputPath}/");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Type generation failed: '.$e->getMessage());

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function displayResults(array $results): void
    {
        if (empty($results)) {
            $this->warn('No types were generated. Make sure you have #[GenerateTypes] attributes in your code.');

            return;
        }

        $this->info('📝 Generated '.count($results).' TypeScript types:');

        $groupedResults = [];
        foreach ($results as $result) {
            $groupedResults[$result['file_type']][] = $result;
        }

        foreach ($groupedResults as $fileType => $typeResults) {
            $this->line("  📂 {$fileType}: ".count($typeResults).' types');
            foreach ($typeResults as $result) {
                $this->line("    ✓ {$result['name']}");
            }
        }
    }

    private function displayAnalysisStats(): void
    {
        $this->info("\n📊 Analysis Statistics:");

        $threshold = config('types-generator.aggregation.similarity_threshold', 0.75);
        $minOccurrence = config('types-generator.aggregation.minimum_occurrence', 2);

        $this->line("  • Similarity threshold: {$threshold}");
        $this->line("  • Minimum occurrence: {$minOccurrence}");
        $fileExtension = config('types-generator.files.extension', 'ts');
        $this->line('  • Common types file: '.config('types-generator.aggregation.commons_file_name', 'common').'.'.$fileExtension);
        $this->line('  • Index file: '.config('types-generator.aggregation.index_file_name', 'index').'.'.$fileExtension);
    }

    private function displayDryRunResults(array $results): void
    {
        if (empty($results)) {
            $this->warn('No types would be generated.');

            return;
        }

        $this->info('Would generate '.count($results).' TypeScript types:');

        foreach ($results as $result) {
            $this->line("  → {$result['name']} ({$result['file_type']}) -> {$result['path']}");

            if ($this->output->isVerbose()) {
                $this->line('    Content preview:');
                $lines = explode("\n", $result['content']);
                foreach (array_slice($lines, 0, 5) as $line) {
                    $this->line('      '.$line);
                }
                if (count($lines) > 5) {
                    $this->line('      ...');
                }
                $this->line('');
            }
        }
    }
}
