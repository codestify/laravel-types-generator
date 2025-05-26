<?php

namespace Codemystify\TypesGenerator\TypeProcessors;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Laravel WhenLoaded Processor
 * Dynamically analyzes whenLoaded() calls to infer relationship types
 */
class LaravelWhenLoadedProcessor implements TypeProcessor
{
    private $parser;

    private $nodeFinder;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->nodeFinder = new NodeFinder;
    }

    public function canProcess(string $property, array $currentType, array $context): bool
    {
        return $currentType['type'] === 'unknown' &&
               isset($context['methodSource']) &&
               str_contains($context['methodSource'], 'whenLoaded');
    }

    public function process(string $property, array $currentType, array $context): ?array
    {
        if (! $this->canProcess($property, $currentType, $context)) {
            return null;
        }

        try {
            $ast = $this->parser->parse($context['methodSource']);
            $whenLoadedCalls = $this->findWhenLoadedCallsForProperty($ast, $property);

            foreach ($whenLoadedCalls as $call) {
                $result = $this->analyzeWhenLoadedCall($call, $context);
                if ($result && $result['type'] !== 'unknown') {
                    return $result;
                }
            }
        } catch (\Exception $e) {
            // Ignore parsing errors
        }

        return null;
    }

    public function getPriority(): int
    {
        return 95; // Very high priority for whenLoaded analysis
    }

    private function findWhenLoadedCallsForProperty(array $ast, string $property): array
    {
        $calls = [];
        $methodCalls = $this->nodeFinder->findInstanceOf($ast, Node\Expr\MethodCall::class);

        foreach ($methodCalls as $call) {
            if ($this->isWhenLoadedCallForProperty($call, $property)) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    private function isWhenLoadedCallForProperty(Node\Expr\MethodCall $call, string $property): bool
    {
        // Check if it's a whenLoaded method call
        if (! ($call->name instanceof Node\Identifier) || $call->name->toString() !== 'whenLoaded') {
            return false;
        }

        // Check if the first argument matches our property (relationship name)
        if (empty($call->args)) {
            return false;
        }

        $firstArg = $call->args[0]->value;
        if ($firstArg instanceof Node\Scalar\String_) {
            return $firstArg->value === $property;
        }

        return false;
    }

    private function analyzeWhenLoadedCall(Node\Expr\MethodCall $call, array $context): ?array
    {
        // If there's a closure as second argument, analyze its structure
        if (count($call->args) >= 2) {
            $closure = $call->args[1]->value;
            if ($closure instanceof Node\Expr\Closure) {
                return $this->analyzeWhenLoadedClosure($closure);
            }
        }

        // Default relationship structure
        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
            ],
            'description' => 'Dynamically loaded relationship',
        ];
    }

    private function analyzeWhenLoadedClosure(Node\Expr\Closure $closure): ?array
    {
        $returnNodes = $this->nodeFinder->findInstanceOf($closure->stmts, Node\Stmt\Return_::class);

        foreach ($returnNodes as $returnNode) {
            if ($returnNode->expr instanceof Node\Expr\Array_) {
                return $this->analyzeArrayStructure($returnNode->expr);
            }
        }

        return null;
    }

    private function analyzeArrayStructure(Node\Expr\Array_ $arrayExpr): array
    {
        $structure = [];

        foreach ($arrayExpr->items as $item) {
            if ($item instanceof Node\Expr\ArrayItem && $item->key && $item->value) {
                $key = $this->getStringValue($item->key);
                if ($key) {
                    $structure[$key] = $this->inferPropertyType($item->value);
                }
            }
        }

        return [
            'type' => 'object',
            'nullable' => true,
            'structure' => $structure,
        ];
    }

    private function inferPropertyType(Node $value): array
    {
        // Basic type inference from AST nodes
        if ($value instanceof Node\Scalar\String_) {
            return ['type' => 'string'];
        }
        if ($value instanceof Node\Scalar\LNumber) {
            return ['type' => 'number'];
        }
        if ($value instanceof Node\Expr\PropertyFetch) {
            return ['type' => 'string']; // Most property accesses return strings
        }

        return ['type' => 'string']; // Default fallback
    }

    private function getStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        return null;
    }
}
