<?php

namespace Codemystify\TypesGenerator\Console;

use Codemystify\TypesGenerator\Services\SimpleTypeGeneratorService;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'types:stats';

    protected $description = 'Show type generation statistics and insights';

    public function __construct(
        private SimpleTypeGeneratorService $typeGeneratorService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Analyzing types...');

        try {
            // Generate types to get statistics
            $results = $this->typeGeneratorService->generateTypes(['dry_run' => true]);

            $stats = $this->calculateStats($results);
            $this->displayStats($stats);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Stats calculation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function calculateStats(array $results): array
    {
        $stats = [
            'total_types' => count($results),
            'by_file_type' => [],
            'by_group' => [],
            'largest_types' => [],
            'file_distribution' => [],
        ];

        foreach ($results as $result) {
            // Count by file type
            $fileType = $result['file_type'];
            $stats['by_file_type'][$fileType] = ($stats['by_file_type'][$fileType] ?? 0) + 1;

            // Count by group
            $group = $result['group'] ?? 'default';
            $stats['by_group'][$group] = ($stats['by_group'][$group] ?? 0) + 1;

            // Track largest types by content length
            $stats['largest_types'][] = [
                'name' => $result['name'],
                'size' => strlen($result['content']),
                'lines' => substr_count($result['content'], "\n"),
            ];
        }

        // Sort largest types
        usort($stats['largest_types'], fn ($a, $b) => $b['size'] <=> $a['size']);
        $stats['largest_types'] = array_slice($stats['largest_types'], 0, 5);

        return $stats;
    }

    private function displayStats(array $stats): void
    {
        $this->info('Type Generation Statistics:');
        $this->line("Total types: {$stats['total_types']}");

        if (! empty($stats['by_file_type'])) {
            $this->line("\nBy file type:");
            foreach ($stats['by_file_type'] as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
        }

        if (! empty($stats['by_group'])) {
            $this->line("\nBy group:");
            foreach ($stats['by_group'] as $group => $count) {
                $this->line("  {$group}: {$count}");
            }
        }

        if (! empty($stats['largest_types'])) {
            $this->line("\nLargest types:");
            foreach ($stats['largest_types'] as $type) {
                $this->line("  {$type['name']}: {$type['size']} bytes, {$type['lines']} lines");
            }
        }
    }
}
