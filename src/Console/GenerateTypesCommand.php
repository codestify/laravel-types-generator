<?php

namespace Codemystify\TypesGenerator\Console;

use Codemystify\TypesGenerator\Services\TypeGeneratorService;
use Illuminate\Console\Command;

class GenerateTypesCommand extends Command
{
    protected $signature = 'generate:types
                           {--force : Force regeneration of all types}
                           {--group= : Generate specific group only}
                           {--watch : Watch for changes and regenerate}';

    protected $description = 'Generate TypeScript types from Laravel Resources and Controllers';

    public function handle(TypeGeneratorService $generator): int
    {
        $this->info('🚀 Starting TypeScript types generation...');

        try {
            $options = [
                'force' => $this->option('force'),
                'group' => $this->option('group'),
                'watch' => $this->option('watch'),
            ];

            if ($options['watch']) {
                return $this->handleWatchMode($generator, $options);
            }

            $results = $generator->generateTypes($options);

            $this->displayResults($results);

            $this->info('✅ TypeScript types generated successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed to generate types: '.$e->getMessage());
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    private function handleWatchMode(TypeGeneratorService $generator, array $options): int
    {
        $this->info('👀 Watching for changes... Press Ctrl+C to stop.');

        // Initial generation
        $generator->generateTypes($options);

        // Watch implementation would go here
        // You could use spatie/file-system-watcher or similar

        return Command::SUCCESS;
    }

    private function displayResults(array $results): void
    {
        $this->table(
            ['Type', 'Source', 'Status'],
            array_map(fn ($result) => [
                $result['name'],
                $result['source'],
                $result['status'] ? '✅' : '❌',
            ], $results)
        );
    }
}
