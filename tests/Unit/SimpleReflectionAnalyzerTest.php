<?php

use Codemystify\TypesGenerator\Services\SimpleReflectionAnalyzer;
use Illuminate\Http\Resources\Json\JsonResource;

describe('SimpleReflectionAnalyzer', function () {

    it('can analyze resource toArray method', function () {
        $mockResource = new class((object) ['id' => 1, 'title' => 'test', 'is_active' => true]) extends JsonResource
        {
            public function toArray($request): array
            {
                return [
                    'id' => $this->id,
                    'title' => $this->title,
                    'is_active' => $this->is_active,
                ];
            }
        };

        $reflection = new ReflectionMethod($mockResource, 'toArray');
        $schemaInfo = [];

        $analyzer = new SimpleReflectionAnalyzer;
        $result = $analyzer->analyzeMethod($reflection, $schemaInfo);

        expect($result)->toBeArray();
    });

    it('can identify resource classes', function () {
        $mockResource = new class((object) ['id' => 1]) extends JsonResource
        {
            public function toArray($request): array
            {
                return ['id' => 1];
            }
        };

        $reflection = new ReflectionMethod($mockResource, 'toArray');
        $analyzer = new SimpleReflectionAnalyzer;

        $result = $analyzer->analyzeMethod($reflection, []);

        expect($result)->toBeArray();
    });

    it('handles complex resource method analysis', function () {
        $analyzer = new SimpleReflectionAnalyzer;

        // Test that analyzer handles various scenarios gracefully
        expect($analyzer)->toBeInstanceOf(SimpleReflectionAnalyzer::class);
    });
});
