<?php

namespace Codemystify\TypesGenerator\Console;

use Codemystify\TypesGenerator\Services\IntelligentTypeAggregator;
use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;
use Illuminate\Console\Command;

class AnalyzeTypesCommand extends Command
{
    protected $signature = 'types:analyze 
                           {--threshold=0.75 : Similarity threshold for type comparison}
                           {--detailed : Show detailed analysis with property patterns}';

    protected $description = 'Analyze types for similarities and suggest intelligent optimizations';

    public function __construct(
        private SimpleTypeGeneratorService $typeGeneratorService,
        private IntelligentTypeAggregator $intelligentAggregator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = (float) $this->option('threshold');
        $detailed = $this->option('detailed');

        $this->info("🔍 Analyzing types with intelligent pattern detection (threshold: {$threshold})...");

        try {
            // Generate types to get the structure data
            $results = $this->typeGeneratorService->generateTypes(['dry_run' => true]);

            if (empty($results)) {
                $this->warn('No types found to analyze.');

                return Command::SUCCESS;
            }

            // Perform intelligent analysis
            $analysis = $this->intelligentAggregator->analyzeTypes($results);

            $this->displayAnalysisResults($analysis, $detailed);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Type analysis failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function displayAnalysisResults(array $analysis, bool $detailed): void
    {
        $metrics = $analysis['optimization_metrics'];

        $this->info("\n📊 Analysis Results:");
        $this->line("  • Total types analyzed: {$metrics['total_types_analyzed']}");
        $this->line("  • Common types found: {$metrics['common_types_extracted']}");
        $this->line("  • Structure groups: {$metrics['structure_groups_found']}");
        $this->line("  • Optimization ratio: {$metrics['optimization_ratio']}%");

        if (! empty($analysis['common_types'])) {
            $this->info("\n🔗 Common Types Identified:");
            foreach ($analysis['common_types'] as $typeName => $properties) {
                $propCount = count($properties);
                $this->line("  • {$typeName} ({$propCount} properties)");

                if ($detailed) {
                    foreach ($properties as $propName => $propDef) {
                        $frequency = $propDef['frequency'] ?? 1;
                        $semantic = $propDef['semantic'] ?? 'general';
                        $this->line("    - {$propName}: {$propDef['type']} (used {$frequency}x, semantic: {$semantic})");
                    }
                }
            }
        }

        if ($detailed && ! empty($analysis['property_analysis'])) {
            $this->displayDetailedPropertyAnalysis($analysis['property_analysis']);
        }

        if (! empty($analysis['type_structures'])) {
            $this->info("\n🏗️  Structural Similarities:");
            foreach ($analysis['type_structures'] as $hash => $group) {
                $typeNames = array_column($group, 'name');
                $this->line('  • Similar structure found in: '.implode(', ', $typeNames));
            }
        }

        $this->info("\n💡 Recommendations:");
        $this->line("  • Run 'php artisan types:generate' to apply optimizations");
        $this->line('  • Common types will be extracted to reduce duplication');
        $this->line('  • Generated index file will organize exports efficiently');
    }

    private function displayDetailedPropertyAnalysis(array $propertyAnalysis): void
    {
        $this->info("\n🧬 Property Pattern Analysis:");

        if (! empty($propertyAnalysis['semantic_groups'])) {
            $this->line('  Semantic Groups:');
            foreach ($propertyAnalysis['semantic_groups'] as $semantic => $properties) {
                if (count($properties) >= 2) {
                    $this->line("    • {$semantic}: ".implode(', ', array_slice($properties, 0, 5)).
                        (count($properties) > 5 ? ' and '.(count($properties) - 5).' more' : ''));
                }
            }
        }

        if (! empty($propertyAnalysis['type_patterns'])) {
            $this->line("\n  Type Patterns:");
            foreach ($propertyAnalysis['type_patterns'] as $pattern => $properties) {
                if (count($properties) >= 2) {
                    $this->line("    • {$pattern}: ".count($properties).' properties');
                }
            }
        }
    }
}
