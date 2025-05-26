<?php

namespace Codemystify\TypesGenerator\Services;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use ReflectionClass;
use ReflectionEnum;

class EnhancedTypeAnalyzer
{
    private $parser;

    private $nodeFinder;

    private array $config;

    private array $modelCache = [];

    private array $enumCache = [];

    private MigrationAnalyzer $migrationAnalyzer;

    public function __construct(MigrationAnalyzer $migrationAnalyzer)
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->nodeFinder = new NodeFinder;
        $this->config = config('types-generator');
        $this->migrationAnalyzer = $migrationAnalyzer;
    }

    /**
     * Analyze a model and cache the results
     */
    public function analyzeModel(string $modelClass): array
    {
        if (isset($this->modelCache[$modelClass])) {
            return $this->modelCache[$modelClass];
        }

        if (! class_exists($modelClass)) {
            return $this->modelCache[$modelClass] = [];
        }

        try {
            $reflection = new ReflectionClass($modelClass);
            $filename = $reflection->getFileName();

            if (! $filename) {
                return $this->modelCache[$modelClass] = [];
            }

            $fileSource = file_get_contents($filename);
            $ast = $this->parser->parse($fileSource);

            $analysis = [
                'casts' => $this->extractCasts($ast),
                'relationships' => $this->extractRelationships($ast, $reflection),
                'fillable' => $this->extractArrayProperty($ast, 'fillable'),
                'hidden' => $this->extractArrayProperty($ast, 'hidden'),
                'table' => $this->getTableName($reflection),
                'migrations' => $this->getMigrationData($this->getTableName($reflection)),
            ];

            return $this->modelCache[$modelClass] = $analysis;

        } catch (\Exception $e) {
            return $this->modelCache[$modelClass] = [];
        }
    }

    /**
     * Analyze an enum class
     */
    public function analyzeEnum(string $enumClass): array
    {
        if (isset($this->enumCache[$enumClass])) {
            return $this->enumCache[$enumClass];
        }

        if (! enum_exists($enumClass)) {
            return $this->enumCache[$enumClass] = [];
        }

        try {
            $reflection = new ReflectionEnum($enumClass);
            $cases = [];

            foreach ($reflection->getCases() as $case) {
                $cases[] = $case->getBackingValue() ?? $case->getName();
            }

            return $this->enumCache[$enumClass] = [
                'values' => $cases,
                'type' => $reflection->getBackingType()?->getName() ?? 'string',
            ];

        } catch (\Exception $e) {
            return $this->enumCache[$enumClass] = [];
        }
    }

    /**
     * Infer property type using model, migration, and enum data
     */
    public function inferPropertyType(string $property, ReflectionClass $resourceClass): array
    {
        // Get the corresponding model
        $modelClass = $this->findCorrespondingModel($resourceClass);
        if (! $modelClass) {
            return $this->getBasicPropertyType($property);
        }

        $modelData = $this->analyzeModel($modelClass);

        // Check model casts first (most authoritative)
        if (isset($modelData['casts'][$property])) {
            return $this->mapCastToType($modelData['casts'][$property]);
        }

        // Check migration data
        if (isset($modelData['migrations']['columns'][$property])) {
            $column = $modelData['migrations']['columns'][$property];

            return $this->mapMigrationColumnToType($column);
        }

        // Fallback to basic inference
        return $this->getBasicPropertyType($property);
    }

    /**
     * Infer relationship type
     */
    public function inferRelationshipType(string $relationName, ReflectionClass $resourceClass): array
    {
        $modelClass = $this->findCorrespondingModel($resourceClass);
        if (! $modelClass) {
            return ['type' => 'unknown'];
        }

        $modelData = $this->analyzeModel($modelClass);

        if (isset($modelData['relationships'][$relationName])) {
            $relationship = $modelData['relationships'][$relationName];

            return $this->mapRelationshipToType($relationship);
        }

        return ['type' => 'unknown'];
    }

    /**
     * Analyze enum value access like $this->status->value
     */
    public function analyzeEnumValueAccess(string $property, ReflectionClass $resourceClass): array
    {
        $modelClass = $this->findCorrespondingModel($resourceClass);
        if (! $modelClass) {
            return ['type' => 'string', 'description' => 'Enum value'];
        }

        $modelData = $this->analyzeModel($modelClass);

        // Check if the property is cast to an enum
        if (isset($modelData['casts'][$property])) {
            $castType = $modelData['casts'][$property];

            // If it's an enum class, analyze the enum
            if (class_exists($castType) && enum_exists($castType)) {
                $enumData = $this->analyzeEnum($castType);

                return [
                    'type' => $enumData['type'],
                    'enum_values' => $enumData['values'],
                    'description' => "Enum value from {$castType}",
                ];
            }
        }

        // For common status/visibility fields, assume string enum
        if (in_array($property, ['status', 'visibility', 'type'])) {
            return ['type' => 'string', 'description' => 'Enum value'];
        }

        return ['type' => 'string', 'description' => 'Enum value'];
    }

    /**
     * Extract casts method from AST
     */
    private function extractCasts(array $ast): array
    {
        $casts = [];

        $methods = $this->nodeFinder->findInstanceOf($ast, ClassMethod::class);

        foreach ($methods as $method) {
            if ($method->name->toString() === 'casts') {
                $returnNodes = $this->nodeFinder->findInstanceOf($method->stmts, Return_::class);

                foreach ($returnNodes as $returnNode) {
                    if ($returnNode->expr instanceof Array_) {
                        $casts = $this->parseArrayExpression($returnNode->expr);
                    }
                }
            }
        }

        return $casts;
    }

    /**
     * Extract relationships from model methods
     */
    private function extractRelationships(array $ast, ReflectionClass $reflection): array
    {
        $relationships = [];

        $methods = $this->nodeFinder->findInstanceOf($ast, ClassMethod::class);

        foreach ($methods as $method) {
            $methodName = $method->name->toString();

            if ($this->isRelationshipMethod($method)) {
                $relationshipType = $this->extractRelationshipType($method);
                if ($relationshipType) {
                    $relationships[$methodName] = $relationshipType;
                }
            }
        }

        return $relationships;
    }

    /**
     * Check if method is a relationship
     */
    private function isRelationshipMethod(ClassMethod $method): bool
    {
        $skipMethods = [
            'casts', 'fillable', 'hidden', 'dates', 'boot', 'booted',
            'getTable', 'getKeyName', 'getRouteKeyName', 'scopeQuery',
        ];

        $methodName = $method->name->toString();

        if (in_array($methodName, $skipMethods) || str_starts_with($methodName, 'get') || str_starts_with($methodName, 'set')) {
            return false;
        }

        $relationshipMethods = [
            'hasOne', 'hasMany', 'belongsTo', 'belongsToMany',
            'morphOne', 'morphMany', 'morphTo', 'morphToMany',
        ];

        $methodCalls = $this->nodeFinder->findInstanceOf($method->stmts, Node\Expr\MethodCall::class);

        foreach ($methodCalls as $call) {
            if ($call->name instanceof Node\Identifier) {
                $callName = $call->name->toString();
                if (in_array($callName, $relationshipMethods)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract relationship type and target model
     */
    private function extractRelationshipType(ClassMethod $method): ?array
    {
        $methodCalls = $this->nodeFinder->findInstanceOf($method->stmts, Node\Expr\MethodCall::class);

        foreach ($methodCalls as $call) {
            if ($call->name instanceof Node\Identifier) {
                $callName = $call->name->toString();

                $relatedModel = null;
                if (! empty($call->args)) {
                    $relatedModel = $this->getStringValue($call->args[0]->value);
                }

                return match ($callName) {
                    'hasOne', 'belongsTo', 'morphOne', 'morphTo' => [
                        'type' => 'single',
                        'relation' => $callName,
                        'model' => $relatedModel,
                    ],
                    'hasMany', 'belongsToMany', 'morphMany', 'morphToMany' => [
                        'type' => 'collection',
                        'relation' => $callName,
                        'model' => $relatedModel,
                    ],
                    default => null
                };
            }
        }

        return null;
    }

    /**
     * Parse array expression from AST
     */
    private function parseArrayExpression(Array_ $arrayExpr): array
    {
        $result = [];

        foreach ($arrayExpr->items as $item) {
            if ($item instanceof ArrayItem && $item->key && $item->value) {
                $key = $this->getStringValue($item->key);
                $value = $this->getStringValue($item->value);

                if ($key && $value) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Extract array property like $fillable
     */
    private function extractArrayProperty(array $ast, string $propertyName): array
    {
        // Implementation for extracting array properties
        return [];
    }

    /**
     * Get string value from AST node
     */
    private function getStringValue(Node $node): ?string
    {
        if ($node instanceof Node\Scalar\String_) {
            return $node->value;
        }

        if ($node instanceof Node\Expr\ClassConstFetch) {
            // Handle ClassName::class
            if ($node->class instanceof Node\Name) {
                return $node->class->toString();
            }
        }

        return null;
    }

    /**
     * Get table name from model
     */
    private function getTableName(ReflectionClass $reflection): string
    {
        try {
            $instance = $reflection->newInstanceWithoutConstructor();

            return $instance->getTable();
        } catch (\Exception $e) {
            $className = $reflection->getShortName();

            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)).'s';
        }
    }

    /**
     * Get migration data for table
     */
    private function getMigrationData(string $tableName): array
    {
        return $this->migrationAnalyzer->getTableSchema($tableName) ?? [];
    }

    /**
     * Find corresponding model for resource
     */
    private function findCorrespondingModel(ReflectionClass $resourceClass): ?string
    {
        $resourceName = $resourceClass->getShortName();
        $modelName = str_replace('Resource', '', $resourceName);

        $modelClass = $this->config['namespaces']['models'].'\\'.$modelName;

        return class_exists($modelClass) ? $modelClass : null;
    }

    /**
     * Map model cast to TypeScript type
     */
    private function mapCastToType(string $cast): array
    {
        return match ($cast) {
            'integer', 'int' => ['type' => 'number'],
            'float', 'double', 'decimal' => ['type' => 'number'],
            'string' => ['type' => 'string'],
            'boolean', 'bool' => ['type' => 'boolean'],
            'array', 'json' => ['type' => 'object'],
            'date', 'datetime', 'timestamp' => ['type' => 'string', 'description' => 'Date string'],
            default => $this->handleEnumCast($cast)
        };
    }

    /**
     * Handle enum casts
     */
    private function handleEnumCast(string $cast): array
    {
        if (class_exists($cast) && enum_exists($cast)) {
            $enumData = $this->analyzeEnum($cast);

            return [
                'type' => $enumData['type'],
                'enum_values' => $enumData['values'],
                'description' => "Enum: {$cast}",
            ];
        }

        return ['type' => 'unknown'];
    }

    /**
     * Map migration column to TypeScript type
     */
    private function mapMigrationColumnToType(array $column): array
    {
        $type = match ($column['type']) {
            'integer', 'bigint', 'smallint', 'tinyint' => 'number',
            'decimal', 'float', 'double' => 'number',
            'string', 'text', 'longtext', 'char', 'varchar' => 'string',
            'boolean' => 'boolean',
            'json' => 'object',
            'date', 'datetime', 'timestamp' => 'string',
            default => 'unknown'
        };

        $result = ['type' => $type];

        if ($column['nullable'] ?? false) {
            $result['nullable'] = true;
        }

        return $result;
    }

    /**
     * Map relationship to TypeScript type
     */
    private function mapRelationshipToType(array $relationship): array
    {
        if ($relationship['type'] === 'single') {
            return ['type' => 'object', 'nullable' => true];
        } else {
            return ['type' => 'array', 'items' => ['type' => 'object']];
        }
    }

    /**
     * Analyze specific protected methods for better type inference
     */
    public function analyzeProtectedMethod(string $methodName, ReflectionClass $resourceClass): array
    {
        return match ($methodName) {
            'calculateStats' => [
                'type' => 'object',
                'structure' => [
                    'soldTickets' => ['type' => 'number'],
                    'checkInsToday' => ['type' => 'number'],
                    'conversionRate' => ['type' => 'number'],
                    'totalRevenue' => ['type' => 'number'],
                ],
            ],
            'getTicketTypesWithSales' => [
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
            ],
            'getRecentActivity' => [
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
            ],
            default => ['type' => 'unknown']
        };
    }

    public function getBasicPropertyType(string $property): array
    {
        return match ($property) {
            'id' => ['type' => 'number'],
            'ulid' => ['type' => 'string'],
            'title', 'name', 'description', 'venue_name', 'organizer_name' => ['type' => 'string'],
            'start_date', 'end_date' => ['type' => 'string', 'description' => 'ISO date string'],
            'start_time' => ['type' => 'string', 'description' => 'Formatted time'],
            'formatted_address' => ['type' => 'string', 'nullable' => true],
            'is_active', 'is_featured' => ['type' => 'boolean'],
            'price', 'total_amount' => ['type' => 'number'],

            // Category relationships - common patterns
            'category', 'event_category', 'eventCategory' => [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                ],
            ],

            // Image/Media fields
            'cover_image', 'coverImage', 'image', 'avatar', 'banner', 'logo' => [
                'type' => 'object',
                'nullable' => true,
                'structure' => [
                    'url' => ['type' => 'string'],
                    'alt_text' => ['type' => 'string', 'nullable' => true],
                ],
            ],

            // Status and visibility enums
            'status', 'visibility' => ['type' => 'string', 'description' => 'Enum value'],

            default => ['type' => 'string', 'nullable' => true] // Default fallback for unknown properties
        };
    }

    /**
     * Analyze trait method calls with enhanced context awareness
     */
    public function analyzeTraitMethod(string $methodName, ReflectionClass $resourceClass): array
    {
        return match ($methodName) {
            'getFormattedAddress' => ['type' => 'string', 'nullable' => true],
            'getManageEventData' => [
                'type' => 'object',
                'structure' => [
                    'id' => ['type' => 'string'],
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'start_date' => ['type' => 'string', 'description' => 'ISO date string'],
                    'start_time' => ['type' => 'string', 'description' => 'Formatted time'],
                    'venue_name' => ['type' => 'string'],
                    'visibility' => ['type' => 'string', 'description' => 'Enum value'],
                    'formatted_address' => ['type' => 'string', 'nullable' => true],
                    'status' => ['type' => 'string', 'description' => 'Enum value'],
                    'organizer_name' => ['type' => 'string'],
                    'organization_name' => ['type' => 'string'],
                    'category' => [
                        'type' => 'object',
                        'nullable' => true,
                        'structure' => [
                            'id' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                        ],
                    ],
                    'cover_image' => [
                        'type' => 'object',
                        'nullable' => true,
                        'structure' => [
                            'url' => ['type' => 'string'],
                            'alt_text' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                ],
            ],
            default => ['type' => 'unknown']
        };
    }
}
