<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

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
use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Runtime\DehydratorRuntime;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;

class DehydratorGenerator
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
            if ($context->shouldSkipDehydration($property)) {
                continue;
            }
            $handler = $this->handlers->getHandler($property, $context);
            array_push($stmts, ...$handler->generateDehydration($property, $context));
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

        return new GenerationContext(
            className: $className,
            hasUndefinable: $hasUndefinable,
            properties: $properties,
            isPropertyOnly: $isPropertyOnly,
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
            'implements' => [Ast::className(DehydratorRuntime::class)],
            'stmts' => [
                $this->generateNestedDehydratorsProperty(),
                $this->generateCustomMappersProperty(),
                $this->generateFormattersProperty(),
                $this->generateSetNestedDehydratorMethod(),
                $this->generateSetCustomMapperMethod(),
                $this->generateSetFormatterMethod(),
                $this->generateDehydrateMethod($context, $propertyStmts),
            ],
        ]);

        return Ast::return(new Node\Expr\New_($classNode));
    }

    private function generateNestedDehydratorsProperty(): Stmt\Property
    {
        return Ast::factory()->property('nestedDehydrators')
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

    private function generateSetNestedDehydratorMethod(): Stmt\ClassMethod
    {
        return Ast::factory()->method('setNestedDehydrator')
            ->makePublic()
            ->setReturnType('void')
            ->addParam(Ast::factory()->param('className')->setType('string'))
            ->addParam(Ast::factory()->param('dehydrator')->setType('object'))
            ->addStmt(
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(
                        Ast::property(Ast::var('this'), 'nestedDehydrators'),
                        Ast::var('className')
                    ),
                    Ast::var('dehydrator')
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
    private function generateDehydrateMethod(GenerationContext $context, array $propertyStmts): Stmt\ClassMethod
    {
        $runtimeStmts = [
            Ast::expr(Ast::assign(Ast::var('errors'), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var('result'), Ast::array([]))),
            ...$propertyStmts,
            HydraAst::throwIfErrors(DehydrationFailure::class),
            Ast::return(Ast::var('result')),
        ];
        $stmts = [HydraAst::wrapDehydrationRuntimeBoundary($runtimeStmts)];

        return Ast::factory()->method('dehydrate')
            ->makePublic()
            ->setReturnType('array')
            ->addParam(Ast::factory()->param('object')->setType('object'))
            ->addParam(Ast::factory()->param('path')->setType('string')->setDefault(''))
            ->addStmts($stmts)
            ->getNode()
        ;
    }
}
