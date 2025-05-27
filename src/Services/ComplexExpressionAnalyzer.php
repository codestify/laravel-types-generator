<?php

namespace Codemystify\TypesGenerator\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;

/**
 * Specialized analyzer for complex PHP expressions in Laravel Resources
 */
class ComplexExpressionAnalyzer
{
    private $parser;

    private $nodeFinder;

    private EnhancedTypeAnalyzer $typeAnalyzer;

    public function __construct(EnhancedTypeAnalyzer $typeAnalyzer)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->nodeFinder = new NodeFinder;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * Analyze complex ternary expressions like:
     * is_string($this->visibility) ? $this->visibility : $this->visibility->value
     */
    public function analyzeTernaryExpression(Node\Expr\Ternary $ternary, ReflectionClass $class): array
    {
        // Enhanced enum value access pattern detection
        if ($this->isEnumValueAccessPattern($ternary, $class)) {
            $propertyName = $this->extractPropertyFromTernary($ternary);

            return [
                'type' => 'string',
                'description' => "Enum value from {$propertyName}",
            ];
        }

        // Special pattern for Laravel enum properties
        if ($this->isLaravelEnumPattern($ternary)) {
            return ['type' => 'string', 'description' => 'Enum value'];
        }

        // Analyze both branches
        if ($ternary->if) {
            $ifType = $this->analyzeExpression($ternary->if, $class);
        } else {
            $ifType = $this->analyzeExpression($ternary->cond, $class);
        }

        $elseType = $this->analyzeExpression($ternary->else, $class);

        // If both types are the same, return that type
        if ($ifType['type'] === $elseType['type']) {
            return $ifType;
        }

        return ['type' => 'string'];
    }

    /**
     * Analyze complex whenLoaded closures that access nested properties
     */
    public function analyzeWhenLoadedClosure(Node\Expr\Closure $closure, string $relationName, ReflectionClass $class): array
    {
        $returnNodes = $this->nodeFinder->findInstanceOf($closure->stmts, Node\Stmt\Return_::class);

        foreach ($returnNodes as $returnNode) {
            if ($returnNode->expr) {
                // Handle simple property access like $this->relation->property
                if ($this->isSimplePropertyAccess($returnNode->expr)) {
                    return ['type' => 'string', 'nullable' => true];
                }

                // Handle array returns
                if ($returnNode->expr instanceof Node\Expr\Array_) {
                    return $this->analyzeArrayInClosure($returnNode->expr, $relationName, $class);
                }

                // Handle method calls that might return arrays or objects
                if ($returnNode->expr instanceof Node\Expr\MethodCall) {
                    return $this->analyzeMethodCallReturn($returnNode->expr, $relationName, $class);
                }
            }
        }

        // Enhanced fallback - try to infer from relation name patterns
        return $this->inferFromRelationNamePattern($relationName);
    }

    /**
     * Analyze method call returns in whenLoaded closures
     */
    private function analyzeMethodCallReturn(Node\Expr\MethodCall $methodCall, string $relationName, ReflectionClass $class): array
    {
        // Handle common Laravel collection methods that return arrays
        if ($methodCall->name instanceof Node\Identifier) {
            $methodName = $methodCall->name->toString();

            return match ($methodName) {
                'toArray' => ['type' => 'object', 'nullable' => true],
                'first' => ['type' => 'object', 'nullable' => true],
                'get' => ['type' => 'array', 'items' => ['type' => 'object']],
                default => ['type' => 'unknown']
            };
        }

        return ['type' => 'unknown'];
    }

    /**
     * Infer type from relation name patterns when AST analysis fails
     */
    private function inferFromRelationNamePattern(string $relationName): array
    {
        // Common relationship patterns to object structures
        if (str_contains($relationName, 'Category') || str_contains($relationName, 'category')) {
            return [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                ],
            ];
        }

        if (str_contains($relationName, 'Image') || str_contains($relationName, 'image')) {
            return [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'url' => ['type' => 'string'],
                    'alt_text' => ['type' => 'string', 'nullable' => true],
                ],
            ];
        }

        // Default object structure
        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * Analyze private methods using generic Laravel patterns only - NO hardcoded assumptions
     */
    public function analyzePrivateMethod(ReflectionMethod $method, ReflectionClass $class): array
    {
        $methodName = strtolower($method->getName());

        // Use generic Laravel naming patterns only - NO domain-specific assumptions
        return match (true) {
            // Methods ending with 'Stats' typically return objects
            str_ends_with($methodName, 'stats') => [
                'type' => 'object',
                'structure' => [], // Let AST analysis determine actual structure
            ],
            // Methods starting with 'get' and ending with 's' typically return arrays
            str_starts_with($methodName, 'get') && str_ends_with($methodName, 's') => [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ],
            // Methods starting with 'get' typically return objects
            str_starts_with($methodName, 'get') => [
                'type' => 'object',
            ],
            // Default: delegate to AST analysis
            default => ['type' => 'unknown']
        };
    }

    /**
     * Check if ternary is an enum value access pattern
     */
    private function isEnumValueAccessPattern(Node\Expr\Ternary $ternary, ReflectionClass $class): bool
    {
        // Look for pattern: condition ? $this->property : $this->property->value
        if ($ternary->else instanceof MethodCall &&
            $ternary->else->var instanceof PropertyFetch &&
            $ternary->else->name instanceof Node\Identifier &&
            $ternary->else->name->toString() === 'value') {

            return true;
        }

        return false;
    }

    /**
     * Check if ternary follows Laravel enum pattern like is_string($property) ? $property : $property->value
     */
    private function isLaravelEnumPattern(Node\Expr\Ternary $ternary): bool
    {
        // Check if condition is is_string() function call
        if ($ternary->cond instanceof Node\Expr\FuncCall &&
            $ternary->cond->name instanceof Node\Name &&
            $ternary->cond->name->toString() === 'is_string') {

            // Check if the else branch accesses ->value
            if ($ternary->else instanceof MethodCall &&
                $ternary->else->name instanceof Node\Identifier &&
                $ternary->else->name->toString() === 'value') {

                return true;
            }
        }

        return false;
    }

    /**
     * Extract property name from ternary expression
     */
    private function extractPropertyFromTernary(Node\Expr\Ternary $ternary): string
    {
        if ($ternary->else instanceof MethodCall &&
            $ternary->else->var instanceof PropertyFetch &&
            $ternary->else->var->name instanceof Node\Identifier) {

            return $ternary->else->var->name->toString();
        }

        return 'unknown';
    }

    /**
     * Check if expression is simple property access like $this->relation->property
     */
    private function isSimplePropertyAccess(Node $expr): bool
    {
        return $expr instanceof PropertyFetch &&
            $expr->var instanceof PropertyFetch &&
            $expr->var->var instanceof Variable &&
            $expr->var->var->name === 'this';
    }

    /**
     * Analyze array returns in whenLoaded closures
     */
    private function analyzeArrayInClosure(Node\Expr\Array_ $arrayExpr, string $relationName, ReflectionClass $class): array
    {
        $structure = [];

        foreach ($arrayExpr->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->key && $item->value) {
                $key = $this->getStringValue($item->key);

                if ($key) {
                    // Enhanced analysis - analyze the actual value expression to determine type
                    $structure[$key] = $this->analyzeArrayValueExpression($item->value, $relationName);
                }
            }
        }

        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => $structure,
        ];
    }

    /**
     * Analyze array value expressions generically to determine TypeScript type
     */
    private function analyzeArrayValueExpression(Node\Expr $valueExpr, string $relationName): array
    {
        // Property access like $this->relation->id
        if ($valueExpr instanceof PropertyFetch) {
            return $this->analyzePropertyFetchExpression($valueExpr);
        }

        // Method call like $this->relation->getName()
        if ($valueExpr instanceof MethodCall) {
            return $this->analyzeMethodCallExpression($valueExpr);
        }

        // Ternary operator like $this->images->first()?->path ?? asset('...')
        if ($valueExpr instanceof Node\Expr\Ternary || $valueExpr instanceof Node\Expr\BinaryOp\Coalesce) {
            return $this->analyzeTernaryOrNullCoalesceExpression($valueExpr);
        }

        // Array literal like ['item1', 'item2']
        if ($valueExpr instanceof Node\Expr\Array_) {
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        // String literal like 'value'
        if ($valueExpr instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }

        // Number literal like 123
        if ($valueExpr instanceof Node\Scalar\LNumber || $valueExpr instanceof Node\Scalar\DNumber) {
            return ['type' => 'number'];
        }

        // Boolean literal like true/false
        if ($valueExpr instanceof Node\Expr\ConstFetch) {
            $constName = strtolower($valueExpr->name->toString());
            if (in_array($constName, ['true', 'false'])) {
                return ['type' => 'boolean'];
            }
        }

        // Function calls like asset()
        if ($valueExpr instanceof Node\Expr\FuncCall && $valueExpr->name instanceof Node\Name) {
            $funcName = $valueExpr->name->toString();
            if ($funcName === 'asset') {
                return ['type' => 'string', 'description' => 'Asset URL'];
            }
        }

        // Fallback to relationship property inference
        return $this->inferRelationPropertyType('unknown', $relationName);
    }

    /**
     * Analyze property fetch expressions like $this->relation->property
     */
    private function analyzePropertyFetchExpression(PropertyFetch $propertyFetch): array
    {
        // Get the property name being accessed
        $propertyName = $propertyFetch->name instanceof Node\Identifier
            ? $propertyFetch->name->toString()
            : 'unknown';

        // Common property name patterns
        // Use basic type inference based on minimal patterns only
        return match (true) {
            str_contains($propertyName, 'id') || str_contains($propertyName, 'ulid') => ['type' => 'string'],
            str_starts_with($propertyName, 'is_') || str_starts_with($propertyName, 'has_') => ['type' => 'boolean'],
            default => ['type' => 'string'], // Safe default
        };
    }

    /**
     * Analyze method call expressions like $this->relation->getValue()
     */
    private function analyzeMethodCallExpression(MethodCall $methodCall): array
    {
        $methodName = $methodCall->name instanceof Node\Identifier
            ? $methodCall->name->toString()
            : 'unknown';

        return match ($methodName) {
            'toArray' => ['type' => 'array'],
            'toJson' => ['type' => 'string'],
            default => ['type' => 'string'], // Safe default
        };
    }

    /**
     * Analyze ternary or null coalesce expressions
     */
    private function analyzeTernaryOrNullCoalesceExpression(Node\Expr $expr): array
    {
        // These expressions can return null, so mark as nullable
        if ($expr instanceof Node\Expr\Ternary) {
            // Analyze the true branch for type
            if ($expr->if) {
                $type = $this->analyzeArrayValueExpression($expr->if, '');
            } else {
                $type = $this->analyzeArrayValueExpression($expr->else, '');
            }
        } elseif ($expr instanceof Node\Expr\BinaryOp\Coalesce) {
            // Analyze the left side for primary type
            $type = $this->analyzeArrayValueExpression($expr->left, '');
        } else {
            $type = ['type' => 'string'];
        }

        // Mark as nullable since these operators handle null cases
        $type['nullable'] = true;

        return $type;
    }

    /**
     * Infer property types for relations
     */
    private function inferRelationPropertyType(string $property, string $relationName): array
    {
        return match ($property) {
            'id' => ['type' => 'string'], // ULIDs are strings
            'ulid' => ['type' => 'string'],
            'name', 'title', 'slug', 'description' => ['type' => 'string'],
            'url', 'path', 'alt_text' => ['type' => 'string'],
            'is_active', 'is_featured', 'is_verified' => ['type' => 'boolean'],
            'created_at', 'updated_at' => ['type' => 'string'],
            default => ['type' => 'unknown']
        };
    }

    /**
     * Get string value from AST node
     */
    private function getStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        return null;
    }

    /**
     * Basic expression analysis for internal use
     */
    private function analyzeExpression(Node $expr, ReflectionClass $class): array
    {
        // Handle property fetch like $this->property
        if ($expr instanceof PropertyFetch &&
            $expr->var instanceof Variable &&
            $expr->var->name === 'this' &&
            is_string($expr->name->name ?? null)) {

            $propertyName = $expr->name->name;

            return $this->typeAnalyzer->getBasicPropertyType($propertyName);
        }

        // Handle string literals
        if ($expr instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }

        // Handle method calls like $this->property->value
        if ($expr instanceof MethodCall &&
            $expr->var instanceof PropertyFetch &&
            is_string($expr->name->name ?? null) &&
            $expr->name->name === 'value') {

            return ['type' => 'string', 'description' => 'Enum value'];
        }

        // Default fallback
        return ['type' => 'string'];
    }
}
