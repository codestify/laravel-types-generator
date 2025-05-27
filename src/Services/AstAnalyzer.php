<?php

namespace Codemystify\TypesGenerator\Services;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionMethod;

class AstAnalyzer
{
    private $parser;

    private $nodeFinder;

    private EnhancedTypeAnalyzer $typeAnalyzer;

    private ComplexExpressionAnalyzer $complexAnalyzer;

    private MethodContextAnalyzer $contextAnalyzer;

    public function __construct()
    {
        // Handle both php-parser v4 and v5 compatibility
        $factory = new ParserFactory;

        if (method_exists($factory, 'createForNewestSupportedVersion')) {
            // php-parser v5+
            $this->parser = $factory->createForNewestSupportedVersion();
        } else {
            // php-parser v4 fallback
            $this->parser = $factory->create(ParserFactory::PREFER_PHP7);
        }

        $this->nodeFinder = new NodeFinder;
        $this->typeAnalyzer = new EnhancedTypeAnalyzer(new MigrationAnalyzer);
        $this->complexAnalyzer = new ComplexExpressionAnalyzer($this->typeAnalyzer);
        $this->contextAnalyzer = new MethodContextAnalyzer($this);
    }

    /**
     * Analyze a method's return structure using enhanced context-aware AST analysis
     */
    public function analyzeMethodReturnStructure(ReflectionMethod $method): array
    {
        try {
            // Use context analyzer for comprehensive method analysis
            $contextResult = $this->contextAnalyzer->analyzeMethodWithContext($method, $method->getDeclaringClass());

            if ($contextResult['type'] !== 'unknown') {
                return $contextResult;
            }

            // Try basic AST analysis as fallback
            $filename = $method->getFileName();

            // Skip if the file doesn't exist (e.g., eval'd code)
            if (! $filename || ! file_exists($filename)) {
                return ['type' => 'unknown'];
            }

            $fileSource = file_get_contents($filename);

            $ast = $this->parser->parse($fileSource);

            if ($ast === null) {
                return ['type' => 'unknown'];
            }

            // Find the specific method in the AST
            $methodName = $method->getName();
            $methodNode = $this->findMethodInAST($ast, $methodName);

            if (! $methodNode) {
                return ['type' => 'unknown'];
            }

            // Find return statements in the method
            $returnNodes = $this->nodeFinder->findInstanceOf($methodNode->stmts, Return_::class);

            foreach ($returnNodes as $returnNode) {
                if ($returnNode->expr) {
                    $result = $this->analyzeExpression($returnNode->expr, $method->getDeclaringClass());

                    // If we get a valid result, return it
                    if ($result['type'] !== 'unknown') {
                        return $result;
                    }
                }
            }

            // If AST analysis fails completely, try pattern-based inference for the method
            return $this->inferMethodReturnType($method);

        } catch (Error $e) {
            // Try pattern-based inference as last resort
            return $this->inferMethodReturnType($method);
        }
    }

    /**
     * Find a specific method node in the AST
     */
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

    /**
     * Analyze PHP expressions using AST with enhanced type analysis (public for delegation)
     */
    public function analyzeExpression(Node $expr, ReflectionClass $class): array
    {
        return match (true) {
            $expr instanceof Array_ => $this->analyzeArrayExpression($expr, $class),
            $expr instanceof MethodCall => $this->analyzeMethodCall($expr, $class),
            $expr instanceof PropertyFetch => $this->analyzePropertyFetch($expr, $class),
            $expr instanceof Variable => $this->analyzeVariable($expr),
            $expr instanceof Node\Expr\BinaryOp\Coalesce => $this->analyzeCoalesceExpression($expr, $class),
            $expr instanceof Node\Expr\Ternary => $this->analyzeTernaryExpression($expr, $class),
            $expr instanceof Node\Expr\Cast => $this->analyzeCastExpression($expr, $class),
            $expr instanceof Node\Expr\FuncCall => $this->analyzeFunctionCall($expr),
            default => $this->analyzeGenericExpression($expr)
        };
    }

    /**
     * Analyze array expressions from AST with enhanced variable tracing
     */
    private function analyzeArrayExpression(Array_ $arrayExpr, ReflectionClass $class): array
    {
        $structure = [];

        foreach ($arrayExpr->items as $item) {
            if ($item instanceof ArrayItem && $item->key && $item->value) {
                $key = $this->getStringValue($item->key);
                if ($key) {
                    $structure[$key] = $this->analyzeExpression($item->value, $class);
                }
            }
        }

        return [
            'type' => 'object',
            'structure' => $structure,
        ];
    }

    /**
     * Analyze method calls like $this->getManageEventData() with enhanced detection
     */
    private function analyzeMethodCall(MethodCall $methodCall, ReflectionClass $class): array
    {
        // Check if it's $this->methodName()
        if ($methodCall->var instanceof Variable &&
            $methodCall->var->name === 'this' &&
            is_string($methodCall->name->name ?? null)) {

            $methodName = $methodCall->name->name;

            // Special handling for whenLoaded calls
            if ($methodName === 'whenLoaded') {
                return $this->analyzeWhenLoadedCall($methodCall, $class);
            }

            // Try to analyze the target method (including protected methods)
            return $this->analyzeClassMethodInternal($class, $methodName);
        }

        // Handle chained calls like $this->start_date->toISOString() or $this->status->value
        if ($methodCall->var instanceof PropertyFetch) {
            return $this->analyzeChainedMethodCall($methodCall, $class);
        }

        // Handle complex method chains like Model::where()->get()->map()->toArray()
        if ($this->isCollectionChain($methodCall)) {
            return $this->analyzeCollectionChain($methodCall, $class);
        }

        // Handle function calls like asset()
        if ($methodCall->name instanceof Node\Identifier) {
            $functionName = $methodCall->name->toString();
            if ($functionName === 'asset') {
                return ['type' => 'string', 'description' => 'Asset URL'];
            }
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze whenLoaded() method calls with closures using enhanced relationship analysis
     */
    private function analyzeWhenLoadedCall(MethodCall $methodCall, ReflectionClass $class): array
    {
        $args = $methodCall->getArgs();

        if (count($args) < 1) {
            return ['type' => 'unknown'];
        }

        // Get relation name from first argument
        $relationName = $this->getStringValue($args[0]->value);

        // Enhanced detection for specific relationship patterns
        if (count($args) >= 2 && $args[1]->value instanceof Node\Expr\Closure) {
            $closure = $args[1]->value;

            // Try complex analysis first
            $result = $this->complexAnalyzer->analyzeWhenLoadedClosure($closure, $relationName, $class);
            if ($result['type'] !== 'unknown') {
                return $result;
            }
        }

        // Use enhanced relationship analysis for fallback
        $relationshipType = $this->typeAnalyzer->inferRelationshipType($relationName, $class);
        $relationshipType['nullable'] = true; // whenLoaded is always nullable

        return $relationshipType;
    }

    /**
     * Analyze chained method calls like $this->start_date->format() or $this->status->value
     */
    private function analyzeChainedMethodCall(MethodCall $methodCall, ReflectionClass $class): array
    {
        if ($methodCall->var instanceof PropertyFetch &&
            is_string($methodCall->name->name ?? null)) {

            $methodName = $methodCall->name->name;

            // Handle enum value access like $this->status->value
            if ($methodName === 'value' &&
                $methodCall->var->var instanceof Variable &&
                $methodCall->var->var->name === 'this' &&
                is_string($methodCall->var->name->name ?? null)) {

                $propertyName = $methodCall->var->name->name;

                return $this->typeAnalyzer->analyzeEnumValueAccess($propertyName, $class);
            }

            return match ($methodName) {
                'toISOString' => ['type' => 'string', 'description' => 'ISO date string'],
                'format' => ['type' => 'string', 'description' => 'Formatted date/time'],
                'toString' => ['type' => 'string'],
                'toArray' => ['type' => 'array'],
                'count' => ['type' => 'number'],
                default => ['type' => 'string']
            };
        }

        // Handle method chains on method calls like $this->getItems()->toArray()
        if ($methodCall->var instanceof MethodCall &&
            is_string($methodCall->name->name ?? null)) {

            $methodName = $methodCall->name->name;

            if ($methodName === 'toArray') {
                // Analyze the chained method call and convert to array
                $baseResult = $this->analyzeMethodCall($methodCall->var, $class);

                if ($baseResult['type'] === 'object' && isset($baseResult['structure'])) {
                    // Convert object structure to array of objects
                    return [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'structure' => $baseResult['structure'],
                        ],
                    ];
                }

                return ['type' => 'array'];
            }
        }

        return ['type' => 'string'];
    }

    /**
     * Check if this is a Laravel collection method chain
     */
    private function isCollectionChain(MethodCall $methodCall): bool
    {
        // Check if the chain ends with toArray()
        if (is_string($methodCall->name->name ?? null) && $methodCall->name->name === 'toArray') {
            return true;
        }

        // Check if this is part of a chain that includes collection methods
        $current = $methodCall;
        while ($current instanceof MethodCall) {
            $methodName = $current->name->name ?? null;
            if (is_string($methodName) && in_array($methodName, ['get', 'map', 'filter', 'toArray', 'pluck', 'where'])) {
                return true;
            }
            $current = $current->var instanceof MethodCall ? $current->var : null;
        }

        return false;
    }

    /**
     * Analyze Laravel collection method chains
     */
    private function analyzeCollectionChain(MethodCall $methodCall, ReflectionClass $class): array
    {
        // If the chain ends with toArray(), it returns an array
        if (is_string($methodCall->name->name ?? null) && $methodCall->name->name === 'toArray') {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'], // Laravel collections typically contain objects
            ];
        }

        // For other collection methods, return array type
        $methodName = $methodCall->name->name ?? null;
        if (is_string($methodName) && in_array($methodName, ['get', 'map', 'filter', 'pluck'])) {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ];
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze property fetch expressions like $this->title with enhanced model awareness
     */
    private function analyzePropertyFetch(PropertyFetch $propertyFetch, ReflectionClass $class): array
    {
        if ($propertyFetch->var instanceof Variable &&
            $propertyFetch->var->name === 'this' &&
            is_string($propertyFetch->name->name ?? null)) {

            $propertyName = $propertyFetch->name->name;

            // Use enhanced type analyzer for accurate property types
            return $this->typeAnalyzer->inferPropertyType($propertyName, $class);
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze variable expressions
     */
    private function analyzeVariable(Variable $variable): array
    {
        if (is_string($variable->name)) {
            // For now, return unknown to let the context analyzer handle variable tracing
            return ['type' => 'unknown'];
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze null coalescing expressions like $this->organizer_name ?? $this->user->name
     */
    private function analyzeCoalesceExpression(Node\Expr\BinaryOp\Coalesce $coalesce, ReflectionClass $class): array
    {
        // Analyze the left side (primary expression)
        $leftType = $this->analyzeExpression($coalesce->left, $class);

        // The result type should be the left type but not nullable
        $leftType['nullable'] = false;

        return $leftType;
    }

    /**
     * Analyze ternary expressions like is_string($this->visibility) ? $this->visibility : $this->visibility->value
     */
    private function analyzeTernaryExpression(Node\Expr\Ternary $ternary, ReflectionClass $class): array
    {
        return $this->complexAnalyzer->analyzeTernaryExpression($ternary, $class);
    }

    /**
     * Analyze cast expressions like (int) $value
     */
    private function analyzeCastExpression(Node\Expr\Cast $cast, ReflectionClass $class): array
    {
        return match (true) {
            $cast instanceof Node\Expr\Cast\Int_ => ['type' => 'number'],
            $cast instanceof Node\Expr\Cast\Double => ['type' => 'number'],
            $cast instanceof Node\Expr\Cast\String_ => ['type' => 'string'],
            $cast instanceof Node\Expr\Cast\Bool_ => ['type' => 'boolean'],
            $cast instanceof Node\Expr\Cast\Array_ => ['type' => 'array'],
            $cast instanceof Node\Expr\Cast\Object_ => ['type' => 'object'],
            default => ['type' => 'unknown']
        };
    }

    /**
     * Analyze function calls like asset(), round(), etc.
     */
    private function analyzeFunctionCall(Node\Expr\FuncCall $funcCall): array
    {
        if ($funcCall->name instanceof Node\Name) {
            $functionName = $funcCall->name->toString();

            return match ($functionName) {
                'asset' => ['type' => 'string', 'description' => 'Asset URL'],
                'url' => ['type' => 'string', 'description' => 'URL'],
                'route' => ['type' => 'string', 'description' => 'Route URL'],
                'round' => ['type' => 'number'],
                'abs' => ['type' => 'number'],
                'count' => ['type' => 'number'],
                'strlen' => ['type' => 'number'],
                'implode' => ['type' => 'string'],
                'json_encode' => ['type' => 'string'],
                'json_decode' => ['type' => 'object'],
                'array_filter' => ['type' => 'array'],
                'array_map' => ['type' => 'array'],
                default => ['type' => 'unknown']
            };
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze generic expressions with enhanced type detection
     */
    private function analyzeGenericExpression(Node $expr): array
    {
        // Handle string literals
        if ($expr instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }

        // Handle integer literals
        if ($expr instanceof Node\Scalar\LNumber) {
            return ['type' => 'number'];
        }

        // Handle float literals
        if ($expr instanceof Node\Scalar\DNumber) {
            return ['type' => 'number'];
        }

        // Handle boolean literals
        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $expr->name->toString();
            if (in_array(strtolower($name), ['true', 'false'])) {
                return ['type' => 'boolean'];
            }
            if (strtolower($name) === 'null') {
                return ['type' => 'null'];
            }
        }

        return ['type' => 'unknown'];
    }

    /**
     * Try to get string value from a node
     */
    private function getStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        // Handle identifiers (unquoted array keys)
        if ($node instanceof Node\Identifier) {
            return $node->toString();
        }

        // Handle scalar integers as keys
        if ($node instanceof Node\Scalar\LNumber) {
            return (string) $node->value;
        }

        return null;
    }

    /**
     * Infer method return type based on method name and signature when AST analysis fails
     */
    private function inferMethodReturnType(ReflectionMethod $method): array
    {
        $methodName = strtolower($method->getName());

        // Pattern 1: Methods that likely return arrays based on naming
        if (str_ends_with($methodName, 's') && ! str_ends_with($methodName, 'ss')) {
            return ['type' => 'array'];
        }

        // Pattern 2: Default fallback
        return ['type' => 'unknown'];
    }

    /**
     * Public method to analyze a class method (used by SimpleReflectionAnalyzer)
     */
    public function analyzeClassMethod(ReflectionClass $class, string $methodName): array
    {
        return $this->analyzeClassMethodInternal($class, $methodName);
    }

    /**
     * Analyze a method in the class or its traits with dynamic context analysis
     */
    private function analyzeClassMethodInternal(ReflectionClass $class, string $methodName): array
    {
        // Generic pattern-based trait method detection
        if (str_contains(strtolower($methodName), 'address') && str_contains(strtolower($methodName), 'formatted')) {
            return ['type' => 'string', 'nullable' => true];
        }

        if (str_contains(strtolower($methodName), 'data') && str_contains(strtolower($methodName), 'manage')) {
            return $this->typeAnalyzer->analyzeTraitMethod($methodName, $class);
        }

        // First check if method exists in the class itself
        if ($class->hasMethod($methodName)) {
            $method = $class->getMethod($methodName);

            // Use context analyzer for comprehensive analysis
            $result = $this->contextAnalyzer->analyzeMethodWithContext($method, $class);
            if ($result['type'] !== 'unknown') {
                return $result;
            }

            // Try direct AST analysis of the method
            $astResult = $this->analyzeMethodReturnStructure($method);
            if ($astResult['type'] !== 'unknown') {
                return $astResult;
            }
        }

        // Check traits for the method
        foreach ($class->getTraits() as $trait) {
            if ($trait->hasMethod($methodName)) {
                $method = $trait->getMethod($methodName);

                // Enhanced trait method analysis using generic patterns
                if (str_contains(strtolower($methodName), 'data') && str_contains(strtolower($methodName), 'manage')) {
                    return $this->typeAnalyzer->analyzeTraitMethod($methodName, $class);
                }

                // Use context analyzer for trait methods too
                $result = $this->contextAnalyzer->analyzeMethodWithContext($method, $trait);
                if ($result['type'] !== 'unknown') {
                    return $result;
                }

                // Try direct AST analysis for trait methods
                $astResult = $this->analyzeMethodReturnStructure($method);
                if ($astResult['type'] !== 'unknown') {
                    return $astResult;
                }
            }
        }

        return ['type' => 'unknown']; // Let processors handle this
    }
}
