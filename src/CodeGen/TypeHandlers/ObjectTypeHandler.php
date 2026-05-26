<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node;
use PhpParser\Node\Stmt;

final class ObjectTypeHandler implements TypeHandler
{
    public function __construct(
        private readonly ClassAnalyzer $analyzer,
    ) {}

    public function priority(): int
    {
        return 70;
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->hasAttribute(ObjectType::class);
    }

    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        /** @var ObjectType $attr */
        $attr = $property->getAttribute(ObjectType::class);
        $baseType = $attr->value ?? $property->getPrimaryType();

        if ($baseType !== null && $this->analyzer->isObjectType($baseType)) {
            $context->registerNestedType($baseType);
        }
        foreach ($attr->mapping ?? [] as $mappedClass) {
            $context->registerNestedType($mappedClass);
        }

        $hydrateStmts = $this->hydrateStmts($name, $key, $path, $attr, $baseType);

        if ($property->isUndefinable) {
            return [Ast::if(HydraAst::keyExists($key), $hydrateStmts)];
        }
        if ($property->isRequired()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
                $hydrateStmts
            )];
        }
        if ($property->isNullable && !$property->hasAnyDefault()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
                [Ast::if(Ast::identical(HydraAst::dataAccess($key), Ast::null()), [HydraAst::assignArg($name, Ast::null())], $hydrateStmts)]
            )];
        }

        return [Ast::if(
            HydraAst::keyExists($key),
            $property->isNullable
                ? [Ast::if(Ast::identical(HydraAst::dataAccess($key), Ast::null()), [HydraAst::assignArg($name, Ast::null())], $hydrateStmts)]
                : $hydrateStmts,
            [HydraAst::assignArg($name, Ast::toExpr($property->getEffectiveDefault()))]
        )];
    }

    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $path = HydraAst::pathExpr($name);

        /** @var ObjectType $attr */
        $attr = $property->getAttribute(ObjectType::class);
        $baseType = $attr->value ?? $property->getPrimaryType();

        if ($baseType !== null && $this->analyzer->isObjectType($baseType)) {
            $context->registerNestedType($baseType);
        }
        foreach ($attr->mapping ?? [] as $mappedClass) {
            $context->registerNestedType($mappedClass);
        }

        $dehydrateStmts = $this->dehydrateStmts($name, $outputKey, $path, $attr, $baseType);

        if ($property->isNullable) {
            return [Ast::if(
                Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                [HydraAst::assignResult($outputKey, Ast::null())],
                $dehydrateStmts
            )];
        }

        return $dehydrateStmts;
    }

    /**
     * @return array<int, Stmt>
     */
    private function hydrateStmts(string $name, string $key, Node\Expr $path, ObjectType $attr, ?string $baseType): array
    {
        if ($attr->discriminator !== null && $attr->mapping !== null) {
            $disc = $attr->discriminator;
            $payloadVar = Ast::var("payload_{$name}");
            $discVar = Ast::var("disc_{$name}");
            $switchCases = [];

            foreach ($attr->mapping as $discValue => $mappedClass) {
                $switchCases[] = new Stmt\Case_(Ast::string($discValue), [
                    HydraAst::wrapInHydrationCatch([HydraAst::assignArg(
                        $name,
                        Ast::methodCall(
                            Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedHydrators'), $mappedClass),
                            'hydrate',
                            [$payloadVar, $path]
                        )
                    )]),
                    new Stmt\Break_(),
                ]);
            }
            $switchCases[] = new Stmt\Case_(null, [
                HydraAst::appendHydrationError(
                    'INVALID_VALUE',
                    Ast::concat(Ast::string("Unknown discriminator value for '{$disc}': "), Ast::funcCall('json_encode', [$discVar])),
                    $disc,
                    $path
                ),
                new Stmt\Break_(),
            ]);

            return [Ast::if(
                Ast::not(HydraAst::isArray($key)),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(Ast::string("Expected array for nested object '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                    $name,
                    $path
                )],
                [
                    Ast::expr(Ast::assign($payloadVar, HydraAst::dataAccess($key))),
                    Ast::if(
                        Ast::not(Ast::funcCall('array_key_exists', [Ast::string($disc), $payloadVar])),
                        [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing discriminator '{$disc}'"), $disc, $path)],
                        [
                            Ast::expr(Ast::assign($discVar, Ast::arrayAccess($payloadVar, $disc))),
                            ...($attr->passDiscriminator ? [] : [new Stmt\Unset_([Ast::arrayAccess($payloadVar, $disc)])]),
                            new Stmt\Switch_($discVar, $switchCases),
                        ]
                    ),
                ]
            )];
        }

        if ($baseType === null) {
            throw new \InvalidArgumentException("Cannot resolve target type for ObjectType '{$name}'");
        }

        return [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($baseType, $key, $path))])];
    }

    /**
     * @return array<int, Stmt>
     */
    private function dehydrateStmts(string $name, string $outputKey, Node\Expr $path, ObjectType $attr, ?string $baseType): array
    {
        if ($attr->discriminator !== null && $attr->mapping !== null) {
            $disc = $attr->discriminator;
            $classVar = Ast::var("class_{$name}");
            $payloadVar = Ast::var("payload_{$name}");
            $switchCases = [];

            foreach ($attr->mapping as $discValue => $mappedClass) {
                $switchCases[] = new Stmt\Case_(Ast::classConst($mappedClass, 'class'), [
                    HydraAst::wrapInDehydrationCatch([
                        Ast::expr(Ast::assign(
                            $payloadVar,
                            Ast::methodCall(
                                Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedDehydrators'), $mappedClass),
                                'dehydrate',
                                [HydraAst::objectProperty($name), $path]
                            )
                        )),
                        Ast::expr(Ast::assign(Ast::arrayAccess($payloadVar, $disc), Ast::string($discValue))),
                        HydraAst::assignResult($outputKey, $payloadVar),
                    ]),
                    new Stmt\Break_(),
                ]);
            }
            $switchCases[] = new Stmt\Case_(null, [
                Ast::throw(Ast::new('\InvalidArgumentException', [
                    Ast::concat(Ast::string("Unsupported mapped class for '{$name}': "), $classVar),
                ])),
            ]);

            return [
                Ast::expr(Ast::assign($classVar, Ast::funcCall('get_class', [HydraAst::objectProperty($name)]))),
                new Stmt\Switch_($classVar, $switchCases),
            ];
        }

        if ($baseType === null) {
            throw new \InvalidArgumentException("Cannot resolve target type for ObjectType '{$name}'");
        }

        return [HydraAst::wrapInDehydrationCatch([HydraAst::assignResult($outputKey, HydraAst::nestedDehydrate($baseType, $name, $path))])];
    }
}
