<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

use DrabekDigital\Hydra\Attributes\AllowExtraKeys;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ArrayOfTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\BackedEnumTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\CustomFormatterTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\CustomMapperTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\DateTimeTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\DateTimeZoneTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ListOfTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\MapOfTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\NestedObjectTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ObjectTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ScalarTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\TypeHandlerRegistry;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Runtime\HydratorRuntime;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

class HydratorGenerator
{
    private readonly TypeHandlerRegistry $handlers;
    private readonly PrettyPrinter $printer;

    public function __construct(
        private readonly ClassAnalyzer $analyzer,
        ?TypeHandlerRegistry $handlers = null,
    ) {
        $this->handlers = $handlers ?? $this->createDefaultHandlers();
        $this->printer = new PrettyPrinter(['shortArraySyntax' => true]);
    }

    /**
     * @param class-string $className
     */
    public function generate(string $className): string
    {
        $properties = $this->analyzer->analyze($className);
        $context = $this->createContext($className, $properties);

        // Single pass: handlers register nested types and return statements
        $propertyStmts = $this->generateAllPropertyStatements($context);

        $stmts = [$this->generateReturnAnonymousClass($context, $propertyStmts)];

        return "<?php declare(strict_types=1);\n\n".$this->printer->prettyPrint($stmts);
    }

    /**
     * @return Stmt[]
     */
    private function generateAllPropertyStatements(GenerationContext $context): array
    {
        $stmts = [];
        foreach ($context->properties as $property) {
            if ($context->shouldSkipHydration($property)) {
                continue;
            }
            $handler = $this->handlers->getHandler($property, $context);
            array_push($stmts, ...$handler->generateHydration($property, $context));
        }

        return $stmts;
    }

    /**
     * @param class-string            $className
     * @param array<PropertyMetadata> $properties
     */
    private function createContext(string $className, array $properties): GenerationContext
    {
        $hasUndefinable = false;
        foreach ($properties as $property) {
            if ($property->isUndefinable) {
                $hasUndefinable = true;

                break;
            }
        }

        // Check if DTO has no constructor or constructor has no parameters
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $isPropertyOnly = ($constructor === null || $constructor->getNumberOfParameters() === 0);
        $allowExtraKeys = $reflection->getAttributes(AllowExtraKeys::class) !== [];

        return new GenerationContext(
            className: $className,
            hasUndefinable: $hasUndefinable,
            properties: $properties,
            isPropertyOnly: $isPropertyOnly,
            allowExtraKeys: $allowExtraKeys,
        );
    }

    private function createDefaultHandlers(): TypeHandlerRegistry
    {
        $registry = new TypeHandlerRegistry();
        $registry->register(new CustomMapperTypeHandler());
        $registry->register(new CustomFormatterTypeHandler());
        $registry->register(new BackedEnumTypeHandler());
        $registry->register(new DateTimeTypeHandler());
        $registry->register(new DateTimeZoneTypeHandler());
        $registry->register(new ObjectTypeHandler($this->analyzer));
        $registry->register(new MapOfTypeHandler($this->analyzer));
        $registry->register(new ArrayOfTypeHandler($this->analyzer));
        $registry->register(new ListOfTypeHandler($this->analyzer));
        $registry->register(new NestedObjectTypeHandler($this->analyzer));
        $registry->register(new ScalarTypeHandler());

        return $registry;
    }

    /**
     * @param Stmt[] $propertyStmts
     */
    private function generateReturnAnonymousClass(GenerationContext $context, array $propertyStmts): Stmt\Return_
    {
        // Build anonymous class manually since BuilderFactory doesn't support it
        $classNode = new Stmt\Class_(null, [
            'implements' => [Ast::className(HydratorRuntime::class)],
            'stmts' => [
                $this->generateNestedHydratorsProperty(),
                $this->generateCustomMappersProperty(),
                $this->generateFormattersProperty(),
                $this->generateSetNestedHydratorMethod(),
                $this->generateSetCustomMapperMethod(),
                $this->generateSetFormatterMethod(),
                $this->generateHydrateMethod($context, $propertyStmts),
            ],
        ]);

        return Ast::return(new Node\Expr\New_($classNode));
    }

    private function generateNestedHydratorsProperty(): Stmt\Property
    {
        return Ast::factory()->property('nestedHydrators')
            ->makePrivate()
            ->setType('array')
            ->setDefault([])
            ->getNode()
        ;
    }

    private function generateCustomMappersProperty(): Stmt\Property
    {
        return Ast::factory()->property('customMappers')
            ->makePrivate()
            ->setType('array')
            ->setDefault([])
            ->getNode()
        ;
    }

    private function generateSetNestedHydratorMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setNestedHydrator')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('className')->setType('string'))
            ->addParam(Ast::factory()->param('hydrator')->setType('object'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedHydrators'),
                        Ast::var('className')
                    ),
                    Ast::var('hydrator')
                ))
            )
            ->getNode()
        ;
    }

    private function generateSetCustomMapperMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setCustomMapper')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('className')->setType('string'))
            ->addParam(Ast::factory()->param('mapper')->setType('object'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'customMappers'),
                        Ast::var('className')
                    ),
                    Ast::var('mapper')
                ))
            )
            ->getNode()
        ;
    }

    private function generateFormattersProperty(): Stmt\Property
    {
        return Ast::factory()->property('formatters')
            ->makePrivate()
            ->setType('array')
            ->setDefault([])
            ->getNode()
        ;
    }

    private function generateSetFormatterMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setFormatter')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('propertyName')->setType('string'))
            ->addParam(Ast::factory()->param('hydrate')->setType('?\Closure'))
            ->addParam(Ast::factory()->param('dehydrate')->setType('?\Closure'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'formatters'),
                        Ast::var('propertyName')
                    ),
                    Ast::array([
                        'hydrate' => Ast::var('hydrate'),
                        'dehydrate' => Ast::var('dehydrate'),
                    ])
                ))
            )
            ->getNode()
        ;
    }

    /**
     * @param Stmt[] $propertyStmts
     */
    private function generateHydrateMethod(GenerationContext $context, array $propertyStmts): Stmt\ClassMethod
    {
        $runtimeStmts = [
            Ast::expr(Ast::assign(Ast::var('errors'), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var('args'), Ast::array([]))),
        ];

        if (!$context->allowExtraKeys) {
            $runtimeStmts = [...$runtimeStmts, ...$this->generateUnexpectedKeysGuard($context)];
        }

        $runtimeStmts = [...$runtimeStmts, ...$propertyStmts];
        $runtimeStmts[] = HydraAst::throwIfErrors(HydrationFailure::class);
        $runtimeStmts = [...$runtimeStmts, ...$this->generateConstructorStatements($context)];

        $stmts = [HydraAst::wrapHydrationRuntimeBoundary($runtimeStmts)];

        return Ast::factory()->method('hydrate')
            ->makePublic()
            ->setReturnType(Ast::className($context->className))
            ->addParam(Ast::factory()->param('data')->setType('array'))
            ->addParam(Ast::factory()->param('path')->setType('string')->setDefault(''))
            ->addStmts($stmts)
            ->getNode()
        ;
    }

    /**
     * @return Stmt[]
     */
    private function generateUnexpectedKeysGuard(GenerationContext $context): array
    {
        $allowedKeys = array_map(
            fn (string $key) => Ast::string($key),
            $context->inputKeys()
        );

        $unexpectedPath = Ast::ternary(
            Ast::var('path'),
            Ast::concat(Ast::var('path'), Ast::string('.'), Ast::var('inputKey')),
            Ast::var('inputKey')
        );

        return [
            Ast::expr(Ast::assign(Ast::var('allowedKeys'), Ast::array($allowedKeys))),
            Ast::foreach(
                Ast::funcCall('array_keys', [Ast::var('data')]),
                Ast::var('inputKey'),
                [
                    Ast::if(
                        Ast::not(Ast::funcCall('in_array', [Ast::var('inputKey'), Ast::var('allowedKeys'), Ast::true()])),
                        [
                            HydraAst::appendHydrationError(
                                'UNEXPECTED_KEY',
                                Ast::concat(Ast::string("Unexpected key '"), Ast::var('inputKey'), Ast::string("'")),
                                Ast::var('inputKey'),
                                $unexpectedPath
                            ),
                        ]
                    ),
                ]
            ),
        ];
    }

    /**
     * @return Stmt[]
     */
    private function generateConstructorStatements(GenerationContext $context): array
    {
        // Property-only DTOs (no constructor params) need reflection or direct property assignment
        if ($context->isPropertyOnly) {
            return $this->generatePropertyOnlyConstructorStatements($context);
        }

        if ($context->hasUndefinable) {
            return $this->generateReflectionConstructorStatements($context);
        }

        // Simple case: return new ClassName(name: $args['name'], ...)
        $args = array_map(
            fn (PropertyMetadata $p) => Ast::namedArg($p->name, HydraAst::argAccess($p->name)),
            array_values(array_filter(
                $context->properties,
                fn (PropertyMetadata $property) => !$context->shouldSkipHydration($property)
            ))
        );

        return [Ast::return(Ast::new($context->className, $args))];
    }

    /**
     * Generate constructor for DTOs with no constructor - use direct property assignment.
     *
     * @return Stmt[]
     */
    private function generatePropertyOnlyConstructorStatements(GenerationContext $context): array
    {
        $stmts = [];

        // $object = new ClassName();
        $stmts[] = Ast::expr(Ast::assign(
            Ast::var('object'),
            Ast::new($context->className)
        ));

        // Set each property directly
        foreach ($context->properties as $property) {
            if ($context->shouldSkipHydration($property)) {
                continue;
            }
            $name = $property->name;

            if ($property->isUndefinable) {
                // Only set if present in args
                $stmts[] = Ast::if(
                    Ast::funcCall('array_key_exists', [Ast::string($name), Ast::var('args')]),
                    [
                        Ast::expr(Ast::assign(
                            Ast::property(Ast::var('object'), $name),
                            HydraAst::argAccess($name)
                        )),
                    ]
                );
            } else {
                $stmts[] = Ast::expr(Ast::assign(
                    Ast::property(Ast::var('object'), $name),
                    HydraAst::argAccess($name)
                ));
            }
        }

        // return $object;
        $stmts[] = Ast::return(Ast::var('object'));

        return $stmts;
    }

    /**
     * @return Stmt[]
     */
    private function generateReflectionConstructorStatements(GenerationContext $context): array
    {
        $stmts = [];

        // $ref = new \ReflectionClass(ClassName::class)
        $stmts[] = Ast::expr(Ast::assign(
            Ast::var('ref'),
            Ast::new('\ReflectionClass', [Ast::classConst($context->className, 'class')])
        ));

        // $object = $ref->newInstanceWithoutConstructor()
        $stmts[] = Ast::expr(Ast::assign(
            Ast::var('object'),
            Ast::methodCall(Ast::var('ref'), 'newInstanceWithoutConstructor')
        ));

        // Set each property
        foreach ($context->properties as $property) {
            if ($context->shouldSkipHydration($property)) {
                continue;
            }
            $name = $property->name;

            if ($property->isUndefinable) {
                // Only set if present in args
                $stmts[] = Ast::if(
                    Ast::funcCall('array_key_exists', [Ast::string($name), Ast::var('args')]),
                    [
                        Ast::expr(Ast::assign(
                            Ast::var("prop_{$name}"),
                            Ast::methodCall(Ast::var('ref'), 'getProperty', [Ast::string($name)])
                        )),
                        Ast::expr(Ast::methodCall(
                            Ast::var("prop_{$name}"),
                            'setValue',
                            [Ast::var('object'), HydraAst::argAccess($name)]
                        )),
                    ]
                );
            } else {
                $stmts[] = Ast::expr(Ast::assign(
                    Ast::var("prop_{$name}"),
                    Ast::methodCall(Ast::var('ref'), 'getProperty', [Ast::string($name)])
                ));
                $stmts[] = Ast::expr(Ast::methodCall(
                    Ast::var("prop_{$name}"),
                    'setValue',
                    [Ast::var('object'), HydraAst::argAccess($name)]
                ));
            }
        }

        // return $object;
        $stmts[] = Ast::return(Ast::var('object'));

        return $stmts;
    }
}
