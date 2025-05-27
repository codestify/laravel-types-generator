<?php

/**
 * Types Generator Debug Template
 *
 * Copy this file to debug-types/test.php and modify for specific issues
 */

require_once __DIR__.'/../vendor/autoload.php';

use Codemystify\TypesGenerator\Services\AstAnalyzer;
use Codemystify\TypesGenerator\Services\SimpleReflectionAnalyzer;

// Bootstrap Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// ============ MODIFY THESE FOR YOUR ISSUE ============
$resourceClass = 'App\\Http\\Resources\\YourResource';  // Change this
$methodName = 'toArray';                                // Usually toArray
$specificMethods = ['methodName1', 'methodName2'];      // Methods to test individually
// ===================================================

echo "🔍 Debugging Types Generator Issue\n";
echo "Resource: {$resourceClass}\n\n";

// Create analyzers
$astAnalyzer = new AstAnalyzer;
$reflectionAnalyzer = new SimpleReflectionAnalyzer;

// Test main resource method
echo "=== Analyzing {$methodName} method ===\n";
try {
    $reflection = new ReflectionClass($resourceClass);
    $toArrayMethod = $reflection->getMethod($methodName);
    $result = $reflectionAnalyzer->analyzeMethod($toArrayMethod, []);
    echo json_encode($result, JSON_PRETTY_PRINT)."\n\n";
} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n\n";
}

// Test individual methods
foreach ($specificMethods as $method) {
    echo "=== Analyzing {$method} method ===\n";
    try {
        $reflection = new ReflectionClass($resourceClass);
        if ($reflection->hasMethod($method)) {
            $methodObj = $reflection->getMethod($method);
            $result = $astAnalyzer->analyzeMethodReturnStructure($methodObj);
            echo json_encode($result, JSON_PRETTY_PRINT)."\n\n";
        } else {
            echo "❌ Method {$method} not found\n\n";
        }
    } catch (Exception $e) {
        echo '❌ Error: '.$e->getMessage()."\n\n";
    }
}

echo "✅ Debug complete\n";
echo "Compare output with generated TypeScript in resources/js/types/generated/\n";
