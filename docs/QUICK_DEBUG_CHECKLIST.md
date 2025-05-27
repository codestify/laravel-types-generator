# Quick Debug Checklist for Types Generator

## 🚨 Issue Detection
```bash
# 1. Check generated types
cat resources/js/types/generated/[group].ts

# 2. Look for red flags:
- Generic field names (total, count, percentage) instead of actual ones
- Wrong types (string instead of array) 
- Missing fields that exist in PHP
- Hardcoded structures that don't match code
```

## 🔬 Debug Steps

### Step 1: Create Debug Script
```php
// debug-types/test.php
$reflection = new ReflectionClass($resourceClass);
$method = $reflection->getMethod($methodName);
$result = $analyzer->analyzeMethod($method, []);
echo json_encode($result, JSON_PRETTY_PRINT);
```

### Step 2: Check Analysis Chain
```bash
php debug-types/test.php

# Look for:
- {"type": "unknown"} = AST analysis failed
- Wrong structure = Variable context lost
- Generic patterns = Hardcoded fallbacks triggered
```

### Step 3: Find Root Cause
```php
// Common causes:
1. AST can't parse complex expressions (closures, chains)
2. Variable assignments not tracked through method body
3. Collection methods like ->toArray() not detected  
4. Hardcoded patterns in fallback code
```

## 🛠 Quick Fixes

### For Unknown Types
```php
// Improve AST expression handling
private function analyzeExpression(Node $expr): array {
    return match (true) {
        $expr instanceof Array_ => $this->analyzeArrayExpression($expr),
        $expr instanceof MethodCall => $this->analyzeMethodCall($expr),
        // Add missing expression types here
        default => ['type' => 'unknown']
    };
}
```

### For Wrong Array Detection
```php
// Improve collection chain detection
private function isCollectionChain(MethodCall $methodCall): bool {
    // Check for Laravel collection methods
    $methodName = $methodCall->name->name ?? null;
    return in_array($methodName, ['toArray', 'get', 'map', 'filter']);
}
```

### For Lost Variable Context
```php
// Improve variable tracking
private function buildVariableContext(ClassMethod $methodNode): array {
    $context = [];
    $assignments = $this->nodeFinder->findInstanceOf($methodNode->stmts, Assign::class);
    
    foreach ($assignments as $assignment) {
        if ($assignment->var instanceof Variable) {
            $varName = $assignment->var->name;
            $context[$varName] = $this->analyzeAssignmentValue($assignment->expr);
        }
    }
    
    return $context;
}
```

## ❌ What NOT to Do

```php
// NEVER hardcode field names
if ($methodName === 'getStats') {
    return ['soldTickets' => ['type' => 'number']]; // NO!
}

// NEVER assume domain-specific structures
if (str_contains($property, 'event')) {
    return $this->getEventStructure(); // NO!
}

// NEVER use application-specific patterns
if ($className === 'EventResource') {
    return $this->getEventFields(); // NO!
}
```

## ✅ Correct Approach

```php
// ALWAYS analyze actual code
return $this->astAnalyzer->analyzeMethodReturnStructure($method);

// ALWAYS delegate to proper analyzers
return $this->analyzeExpressionWithContext($expr, $class, $context);

// ALWAYS use generic Laravel patterns only
if (str_ends_with($methodName, 's') && !str_ends_with($methodName, 'ss')) {
    return ['type' => 'array']; // Generic plural pattern
}
```

## 🎯 Success Check

Fixed correctly when:
- ✅ Generated TypeScript matches actual PHP return structure
- ✅ Works with any Laravel app (events, e-commerce, etc.)
- ✅ No hardcoded assumptions about field names or types
- ✅ AST analysis working for complex expressions

**Remember: Better to return `unknown` than guess wrong!**
