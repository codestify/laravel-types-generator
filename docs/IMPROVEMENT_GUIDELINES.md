# Types Generator Improvement Guidelines

## Core Philosophy: Zero Hardcoded Data

The fundamental principle of this package is **NEVER hardcode assumptions about data structures, field names, or return types**. This package must work for any Laravel application in any domain (events, e-commerce, healthcare, etc.) without modification.

## 🚨 Critical Rules

### 1. NO HARDCODED FIELD NAMES
```php
// ❌ WRONG - Hardcoded field assumptions
if ($property === 'stats') {
    return ['soldTickets' => ['type' => 'number']]; // Domain-specific!
}

// ✅ CORRECT - Analyze actual code
return $this->astAnalyzer->analyzeMethodReturnStructure($method);
```

### 2. NO DOMAIN-SPECIFIC PATTERNS
```php
// ❌ WRONG - Event-specific assumptions
if (str_contains($methodName, 'ticket')) {
    return $this->inferEventTicketStructure(); // Only works for event apps!
}

// ✅ CORRECT - Generic Laravel patterns only
if (str_ends_with($methodName, 's') && !str_ends_with($methodName, 'ss')) {
    return ['type' => 'array', 'items' => ['type' => 'object']]; // Works for any plural method
}
```

### 3. NO PRESET DATA STRUCTURES
```php
// ❌ WRONG - Assuming specific structure
private function getStatsStructure(): array {
    return [
        'soldTickets' => ['type' => 'number'], // Event app assumption!
        'revenue' => ['type' => 'number']
    ];
}

// ✅ CORRECT - Extract from actual code
private function analyzeActualReturnStructure(ReflectionMethod $method): array {
    return $this->astAnalyzer->analyzeMethodReturnStructure($method);
}
```

## 🔍 How to Identify Hardcoded Data Issues

### Debug Process
1. **Create test files** for debugging:
```php
// debug-types/test.php
$reflection = new ReflectionClass($resourceClass);
$method = $reflection->getMethod($methodName);
$result = $analyzer->analyzeMethod($method, []);
echo json_encode($result, JSON_PRETTY_PRINT);
```

2. **Look for wrong outputs** in generated TypeScript:
   - Generic field names instead of actual ones
   - Wrong types (string instead of array)
   - Missing fields that exist in PHP code
   - Structures that don't match the actual return

3. **Check analysis chain**:
   - Does AST analysis return `unknown`?
   - Are fallback patterns too specific?
   - Is variable context being lost?

### Warning Signs in Code
```php
// 🚨 RED FLAGS
- Hard-coded return structures in switch/match statements
- Method names in if conditions (unless truly generic)
- Specific field names in return arrays
- Domain terminology (event, user, product, etc.)
- Assumptions about data relationships
```

## 🛠 Debugging Methodology

### 1. Trace the Analysis Chain
```bash
# Generate types and check output
php artisan generate:types --group=test

# Create debug script
php debug-types/test.php

# Compare expected vs actual
```

### 2. AST Analysis First
Always start with AST analysis. If it fails:
```php
// Check why AST fails
1. Can it parse the file?
2. Can it find the method?
3. Can it find return statements?
4. Can it analyze the return expression?
```

### 3. Variable Context Tracking
```php
// Ensure variables are properly tracked through assignments
$stats = $this->calculateStats();  // Should track this assignment
return ['stats' => $stats];        // Should use tracked type
```

## 🏗 Architecture Principles

### Analysis Hierarchy (in order of preference)
1. **AST Analysis** - Parse actual PHP code
2. **Reflection Analysis** - Use PHP reflection
3. **Context Analysis** - Track variable assignments
4. **Generic Laravel Patterns** - Only universal patterns
5. **Unknown Type** - Never guess specific structures

### Delegation Pattern
```php
// Always delegate to more specific analyzers
private function analyzeExpression(Node $expr, ReflectionClass $class): array
{
    // Try specific analyzers first
    if ($expr instanceof Array_) {
        return $this->analyzeArrayExpression($expr, $class);
    }
    
    if ($expr instanceof MethodCall) {
        return $this->analyzeMethodCall($expr, $class);
    }
    
    // Delegate to other analyzers
    return $this->delegateToComplexAnalyzer($expr, $class);
}
```

## 📋 Testing Checklist

### Before Making Changes
- [ ] Create debug script for the failing case
- [ ] Identify exactly where hardcoded data exists
- [ ] Understand the actual PHP code structure
- [ ] Check if AST analysis is working

### After Making Changes
- [ ] Run debug script to verify fix
- [ ] Regenerate types and check output
- [ ] Test with different applications/domains
- [ ] Ensure no new hardcoded assumptions introduced

### Cross-Domain Testing
Test the package with different Laravel applications:
```php
// Should work for ANY domain
- Event management (current)
- E-commerce (products, orders)
- Healthcare (patients, appointments)
- Finance (transactions, accounts)
- Social media (posts, comments)
```

## 🔧 Common Fixes

### 1. Replace Hardcoded Patterns
```php
// ❌ Before
private function inferStatsMethod(): array {
    return [
        'soldTickets' => ['type' => 'number'],
        'revenue' => ['type' => 'number']
    ];
}

// ✅ After
private function analyzeActualMethod(ReflectionMethod $method): array {
    return $this->astAnalyzer->analyzeMethodReturnStructure($method);
}
```

### 2. Improve AST Analysis
```php
// Add support for more PHP expressions
match (true) {
    $expr instanceof Array_ => $this->analyzeArrayExpression($expr, $class),
    $expr instanceof MethodCall => $this->analyzeMethodCall($expr, $class),
    $expr instanceof PropertyFetch => $this->analyzePropertyFetch($expr, $class),
    // Add more cases as needed
    default => ['type' => 'unknown']
};
```

### 3. Better Variable Tracking
```php
// Track variable assignments through method body
private function buildVariableContext(ClassMethod $methodNode): array {
    $context = [];
    $assignments = $this->findAssignments($methodNode);
    
    foreach ($assignments as $assignment) {
        $varName = $assignment->var->name;
        $context[$varName] = $this->analyzeAssignment($assignment->expr);
    }
    
    return $context;
}
```

## 🧪 Example Debug Session

```php
// 1. Issue: Wrong type generated
// Expected: array of objects
// Actual: string | null

// 2. Create debug script
$result = $astAnalyzer->analyzeMethodReturnStructure($method);
// Output: {"type": "unknown"}

// 3. Check why AST fails
// - Method uses complex closure
// - Collection chain not detected
// - toArray() call missed

// 4. Fix: Improve collection chain detection
private function isCollectionChain(MethodCall $methodCall): bool {
    // Detect Laravel collection patterns
    return $this->hasCollectionMethods($methodCall);
}

// 5. Verify fix
// Output: {"type": "array", "items": {"type": "object"}}
```

## 📈 Future Improvements

### Priority Order
1. **Fix AST Analysis Gaps** - Handle more PHP expressions
2. **Improve Context Tracking** - Better variable assignment analysis
3. **Add Laravel Pattern Detection** - Generic framework patterns only
4. **Enhance Error Recovery** - Better fallbacks when analysis fails
5. **Performance Optimization** - Cache analysis results

### What NOT to Add
- Domain-specific knowledge
- Application-specific patterns  
- Hardcoded data structures
- Field name assumptions
- Business logic inference

## 🎯 Success Metrics

A successful fix should:
- ✅ Generate accurate TypeScript types matching actual PHP code
- ✅ Work with any Laravel application without modification
- ✅ Use zero hardcoded assumptions about data
- ✅ Provide detailed analysis through AST/reflection
- ✅ Gracefully handle complex PHP expressions

Remember: **When in doubt, return `unknown` rather than guess wrong!**
