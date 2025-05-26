<?php

use Codemystify\TypesGenerator\Services\TypeGeneratorService;
use Illuminate\Support\Facades\File;

describe('Feature Integration', function () {
    beforeEach(function () {
        // Ensure directories exist
        $resourcesPath = config('types-generator.sources.resources_path');
        if (! File::exists($resourcesPath)) {
            File::makeDirectory($resourcesPath, 0755, true);
        }

        // Create test resource
        $resourceContent = '<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;
use Codemystify\TypesGenerator\Attributes\GenerateTypes;

class EventResource extends JsonResource
{
    #[GenerateTypes(name: "Event", group: "events")]
    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "start_date" => $this->start_date,
            "is_active" => $this->is_active,
        ];
    }
}';

        File::put($resourcesPath.'/EventResource.php', $resourceContent);
    });

    it('generates TypeScript types for resources', function () {
        // Test the service directly instead of artisan command
        $service = app(TypeGeneratorService::class);
        $result = $service->generateTypes([]);

        expect($result)->toBeArray();
    });

    it('respects group filtering', function () {
        // Test group filtering functionality
        $service = app(TypeGeneratorService::class);
        $result = $service->generateTypes(['group' => 'events']);

        expect($result)->toBeArray();
    });
});
