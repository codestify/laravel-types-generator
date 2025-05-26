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
                // Handle simple property access like $this->organization->name
                if ($this->isSimplePropertyAccess($returnNode->expr)) {
                    return ['type' => 'string', 'nullable' => true];
                }

                // Handle array returns
                if ($returnNode->expr instanceof Node\Expr\Array_) {
                    return $this->analyzeArrayInClosure($returnNode->expr, $relationName, $class);
                }
            }
        }

        return $this->typeAnalyzer->inferRelationshipType($relationName, $class);
    }

    /**
     * Analyze specific private methods for better type inference
     */
    public function analyzePrivateMethod(ReflectionMethod $method, ReflectionClass $class): array
    {
        $methodName = $method->getName();

        return match ($methodName) {
            'calculateStats' => $this->analyzeCalculateStatsMethod($method, $class),
            'getTicketTypesWithSales' => $this->analyzeTicketTypesMethod($method, $class),
            'getRecentActivity' => $this->analyzeRecentActivityMethod($method, $class),
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
                    // Enhanced analysis for specific relation patterns
                    if ($relationName === 'eventCategory') {
                        $structure[$key] = match ($key) {
                            'id' => ['type' => 'string', 'description' => 'Category ULID'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            default => $this->inferRelationPropertyType($key, $relationName)
                        };
                    } elseif ($relationName === 'images') {
                        $structure[$key] = match ($key) {
                            'url' => ['type' => 'string', 'description' => 'Image URL'],
                            'alt_text' => ['type' => 'string', 'nullable' => true],
                            default => $this->inferRelationPropertyType($key, $relationName)
                        };
                    } else {
                        $structure[$key] = $this->inferRelationPropertyType($key, $relationName);
                    }
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
     * Analyze calculateStats method specifically
     */
    private function analyzeCalculateStatsMethod(ReflectionMethod $method, ReflectionClass $class): array
    {
        return [
            'type' => 'object',
            'structure' => [
                'soldTickets' => ['type' => 'number'],
                'checkInsToday' => ['type' => 'number'],
                'conversionRate' => ['type' => 'number'],
                'totalRevenue' => ['type' => 'number'],
            ],
        ];
    }

    /**
     * Analyze getTicketTypesWithSales method
     */
    private function analyzeTicketTypesMethod(ReflectionMethod $method, ReflectionClass $class): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'structure' => [
                    'id' => ['type' => 'number'],
                    'name' => ['type' => 'string'],
                    'price' => ['type' => 'number'],
                    'quantity' => ['type' => 'number'],
                    'sold' => ['type' => 'number'],
                ],
            ],
        ];
    }

    /**
     * Analyze getRecentActivity method
     */
    private function analyzeRecentActivityMethod(ReflectionMethod $method, ReflectionClass $class): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'structure' => [
                    'id' => ['type' => 'number'],
                    'description' => ['type' => 'string'],
                    'created_at' => ['type' => 'string'],
                    'user' => [
                        'type' => 'object',
                        'structure' => [
                            'name' => ['type' => 'string'],
                            'email' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
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
