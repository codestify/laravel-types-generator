<?php

namespace Codemystify\TypesGenerator\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;

/**
 * Enhanced method analyzer that traces variable assignments and method calls dynamically
 */
class MethodContextAnalyzer
{
    private $parser;

    private $nodeFinder;

    private AstAnalyzer $astAnalyzer;

    private EnhancedTypeAnalyzer $typeAnalyzer;

    public function __construct(AstAnalyzer $astAnalyzer)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->nodeFinder = new NodeFinder;
        $this->astAnalyzer = $astAnalyzer;
        $this->typeAnalyzer = new EnhancedTypeAnalyzer(new MigrationAnalyzer);
    }

    /**
     * Analyze a method with full context awareness including variable assignments
     */
    public function analyzeMethodWithContext(ReflectionMethod $method, ReflectionClass $class): array
    {
        try {
            $filename = $method->getFileName();

            // Skip if the file doesn't exist
            if (! $filename || ! file_exists($filename)) {
                return ['type' => 'unknown'];
            }

            $fileSource = file_get_contents($filename);
            $ast = $this->parser->parse($fileSource);

            $methodNode = $this->findMethodInAST($ast, $method->getName());
            if (! $methodNode) {
                return ['type' => 'unknown'];
            }

            // Build variable context from assignments
            $variableContext = $this->buildVariableContext($methodNode, $class);

            // Find return statements and analyze with context
            $returnNodes = $this->nodeFinder->findInstanceOf($methodNode->stmts, Node\Stmt\Return_::class);

            foreach ($returnNodes as $returnNode) {
                if ($returnNode->expr) {
                    return $this->analyzeExpressionWithContext($returnNode->expr, $class, $variableContext);
                }
            }

            return ['type' => 'unknown'];
        } catch (\Exception $e) {
            return ['type' => 'unknown'];
        }
    }

    /**
     * Build context of variable assignments in the method
     */
    private function buildVariableContext(Node\Stmt\ClassMethod $methodNode, ReflectionClass $class): array
    {
        $context = [];

        // Find all assignments in the method
        $assignments = $this->nodeFinder->findInstanceOf($methodNode->stmts, Assign::class);

        foreach ($assignments as $assignment) {
            if ($assignment->var instanceof Variable && is_string($assignment->var->name)) {
                $varName = $assignment->var->name;

                // Analyze what this variable is assigned to
                $assignedType = $this->analyzeAssignmentValue($assignment->expr, $class);
                $context[$varName] = $assignedType;
            }
        }

        return $context;
    }

    /**
     * Analyze what a variable is assigned to
     */
    private function analyzeAssignmentValue(Node\Expr $expr, ReflectionClass $class): array
    {
        if ($expr instanceof MethodCall) {
            return $this->analyzeMethodCallDynamic($expr, $class);
        }

        // For other expression types, use basic inference
        return ['type' => 'unknown'];
    }

    /**
     * Analyze method calls dynamically by actually looking up the method
     */
    private function analyzeMethodCallDynamic(MethodCall $methodCall, ReflectionClass $class): array
    {
        if ($methodCall->var instanceof Variable &&
            $methodCall->var->name === 'this' &&
            is_string($methodCall->name->name ?? null)) {

            $methodName = $methodCall->name->name;

            // Look for the method in the current class (including protected methods)
            if ($class->hasMethod($methodName)) {
                $targetMethod = $class->getMethod($methodName);

                // For known patterns, use specific analysis
                $result = $this->analyzeKnownMethod($methodName, $targetMethod, $class);
                if ($result['type'] !== 'unknown') {
                    return $result;
                }

                // Recursively analyze the target method
                return $this->analyzeMethodWithContext($targetMethod, $class);
            }

            // Check traits
            foreach ($class->getTraits() as $trait) {
                if ($trait->hasMethod($methodName)) {
                    $targetMethod = $trait->getMethod($methodName);

                    return $this->analyzeMethodWithContext($targetMethod, $class);
                }
            }
        }

        // Handle chained calls like $this->status->value
        if ($methodCall->var instanceof PropertyFetch) {
            return $this->analyzeChainedCall($methodCall, $class);
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze known method patterns for better type inference
     */
    private function analyzeKnownMethod(string $methodName, ReflectionMethod $method, ReflectionClass $class): array
    {
        // Pattern-based analysis for common Laravel resource methods
        if (str_contains($methodName, 'Stats') || str_contains($methodName, 'calculateStats')) {
            return $this->inferStatsMethod($method);
        }

        if (str_contains($methodName, 'TicketTypes') || str_contains($methodName, 'getTicketTypes')) {
            return $this->inferTicketTypesMethod($method);
        }

        if (str_contains($methodName, 'Activity') || str_contains($methodName, 'getRecentActivity')) {
            return $this->inferActivityMethod($method);
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze chained method calls like $this->status->value with enhanced enum detection
     */
    private function analyzeChainedCall(MethodCall $methodCall, ReflectionClass $class): array
    {
        if ($methodCall->var instanceof PropertyFetch &&
            $methodCall->var->var instanceof Variable &&
            $methodCall->var->var->name === 'this' &&
            is_string($methodCall->name->name ?? null)) {

            $propertyName = $methodCall->var->name->name ?? '';
            $methodName = $methodCall->name->name;

            // Handle enum value access - make this more robust
            if ($methodName === 'value') {
                return [
                    'type' => 'string',
                    'description' => "Enum value from {$propertyName}",
                ];
            }

            // Handle date formatting
            if (in_array($methodName, ['toISOString', 'format'])) {
                return ['type' => 'string', 'description' => 'Formatted date/time'];
            }
        }

        return ['type' => 'string'];
    }

    /**
     * Enhanced expression analysis that handles property access better
     */
    private function analyzeExpressionWithContext(Node\Expr $expr, ReflectionClass $class, array $context): array
    {
        if ($expr instanceof Node\Expr\Array_) {
            return $this->analyzeArrayWithContext($expr, $class, $context);
        }

        if ($expr instanceof Variable && isset($context[$expr->name])) {
            return $context[$expr->name];
        }

        if ($expr instanceof MethodCall) {
            return $this->analyzeMethodCallDynamic($expr, $class);
        }

        // Handle property fetch like $this->title
        if ($expr instanceof PropertyFetch &&
            $expr->var instanceof Variable &&
            $expr->var->name === 'this') {

            $propertyName = $expr->name->name ?? '';

            return $this->typeAnalyzer->getBasicPropertyType($propertyName);
        }

        // Handle more complex expressions
        return $this->delegateToMainAnalyzer($expr, $class);
    }

    /**
     * Delegate complex expressions to main AST analyzer
     */
    private function delegateToMainAnalyzer(Node\Expr $expr, ReflectionClass $class): array
    {
        // For complex expressions like ternary, null coalescing, etc.
        // delegate back to the main AST analyzer
        try {
            return $this->astAnalyzer->analyzeExpression($expr, $class);
        } catch (\Exception $e) {
            return ['type' => 'unknown'];
        }
    }

    /**
     * Basic property type inference for common Laravel patterns
     */
    /**
     * Analyze array expressions with variable context
     */
    private function analyzeArrayWithContext(Node\Expr\Array_ $arrayExpr, ReflectionClass $class, array $context): array
    {
        $structure = [];

        foreach ($arrayExpr->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->key && $item->value) {
                $key = $this->getStringValue($item->key);
                if ($key) {
                    // Use context if the value is a variable
                    if ($item->value instanceof Variable && isset($context[$item->value->name])) {
                        $structure[$key] = $context[$item->value->name];
                    } else {
                        $structure[$key] = $this->analyzeExpressionWithContext($item->value, $class, $context);
                    }
                }
            }
        }

        return [
            'type' => 'object',
            'structure' => $structure,
        ];
    }

    /**
     * Infer stats method return type by analyzing common patterns
     */
    private function inferStatsMethod(ReflectionMethod $method): array
    {
        // Look for common stats patterns in the method source
        $source = $this->getMethodSource($method);

        $structure = [];

        if (strpos($source, 'soldTickets') !== false) {
            $structure['soldTickets'] = ['type' => 'number'];
        }
        if (strpos($source, 'checkInsToday') !== false) {
            $structure['checkInsToday'] = ['type' => 'number'];
        }
        if (strpos($source, 'conversionRate') !== false) {
            $structure['conversionRate'] = ['type' => 'number'];
        }
        if (strpos($source, 'totalRevenue') !== false) {
            $structure['totalRevenue'] = ['type' => 'number'];
        }

        return empty($structure) ? ['type' => 'unknown'] : [
            'type' => 'object',
            'structure' => $structure,
        ];
    }

    /**
     * Infer ticket types method return type
     */
    private function inferTicketTypesMethod(ReflectionMethod $method): array
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
     * Infer activity method return type
     */
    private function inferActivityMethod(ReflectionMethod $method): array
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

    // Helper methods
    private function findMethodInAST(array $ast, string $methodName): ?Node\Stmt\ClassMethod
    {
        $methods = $this->nodeFinder->findInstanceOf($ast, Node\Stmt\ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->name->toString() === $methodName) {
                return $method;
            }
        }

        return null;
    }

    private function getStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        return null;
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine() - 1;
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine;

        $source = file($filename);

        return implode('', array_slice($source, $startLine, $length));
    }
}
