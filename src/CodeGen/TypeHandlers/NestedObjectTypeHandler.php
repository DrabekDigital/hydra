<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles nested object types.
 */
final class NestedObjectTypeHandler implements TypeHandler
{
    public function __construct(
        private readonly ClassAnalyzer $analyzer,
    ) {}

    public function priority(): int
    {
        return 50; // Higher than scalar
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        $primaryType = $property->getPrimaryType();

        return $primaryType !== null && $this->analyzer->isObjectType($primaryType);
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);
        $type = $property->getPrimaryType();
        if ($type === null) {
            throw new \InvalidArgumentException("Missing nested object type for '{$name}'");
        }
        $this->assertNotUnitEnum($type, $name);

        // Register nested type
        $context->registerNestedType($type);

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $type);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path, $type);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path, $type);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $type);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $path = HydraAst::pathExpr($name);
        $type = $property->getPrimaryType();
        if ($type === null) {
            throw new \InvalidArgumentException("Missing nested object type for '{$name}'");
        }
        $this->assertNotUnitEnum($type, $name);

        // Register nested type
        $context->registerNestedType($type);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $path, $type, $context);
        }

        if ($property->isNullable) {
            return $this->generateNullableDehydration($name, $outputKey, $path, $type);
        }

        return [HydraAst::wrapInDehydrationCatch([HydraAst::assignResult($outputKey, HydraAst::nestedDehydrate($type, $name, $path))])];
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Generators
    // ─────────────────────────────────────────────────────────────

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path, string $type): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        $elseStmts = [
            Ast::if(
                Ast::not(HydraAst::isArray($key)),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(
                        Ast::string("Expected array for nested object '{$name}', got "),
                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                    ),
                    $name,
                    $path
                )],
                [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path, string $type): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path),
        ];

        // else: if data is null, assign null; if array, hydrate; otherwise error
        $elseStmts = [
            Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [
                    Ast::if(
                        Ast::not(HydraAst::isArray($key)),
                        [HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected array or null for nested object '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        )],
                        [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
                    ),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $type): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        if ($prop->isNullable) {
            // if key exists: if null, assign null; if array, hydrate; else error
            // else: use default
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(HydraAst::isArray($key)),
                            [HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(
                                    Ast::string("Expected array or null for nested object '{$name}', got "),
                                    Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            )],
                            [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(HydraAst::isArray($key)),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(
                            Ast::string("Expected array for nested object '{$name}', got "),
                            Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    )],
                    [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
                ),
            ];
        }

        $elseStmts = [HydraAst::assignArg($name, $default)];

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $type): array
    {
        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(HydraAst::isArray($key)),
                            [HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(
                                    Ast::string("Expected array or null for nested object '{$name}', got "),
                                    Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            )],
                            [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(HydraAst::isArray($key)),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(
                            Ast::string("Expected array for nested object '{$name}', got "),
                            Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    )],
                    [HydraAst::wrapInHydrationCatch([HydraAst::assignArg($name, HydraAst::nestedHydrate($type, $key, $path))])]
                ),
            ];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Dehydration Generators
    // ─────────────────────────────────────────────────────────────

    /**
     * @return Stmt[]
     */
    private function generateNullableDehydration(string $name, string $outputKey, Expr $path, string $type): array
    {
        return [Ast::if(
            Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
            [HydraAst::wrapInDehydrationCatch([HydraAst::assignResult($outputKey, HydraAst::nestedDehydrate($type, $name, $path))])],
            [HydraAst::assignResult($outputKey, Ast::null())]
        )];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, Expr $path, string $type, GenerationContext $ctx): array
    {
        // Check if property is initialized using reflection
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($ctx->shortName, 'class'),
                Ast::string($name),
            ])
        );

        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);

        if ($prop->isNullable) {
            $thenStmts = [Ast::if(
                Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                [HydraAst::wrapInDehydrationCatch([HydraAst::assignResult($outputKey, HydraAst::nestedDehydrate($type, $name, $path))])],
                [HydraAst::assignResult($outputKey, Ast::null())]
            )];
        } else {
            $thenStmts = [HydraAst::wrapInDehydrationCatch([HydraAst::assignResult($outputKey, HydraAst::nestedDehydrate($type, $name, $path))])];
        }

        // Emit configured fallback for undefined properties when requested.
        $elseStmts = null;
        if ($prop->shouldEmitUndefinedDehydrationValue()) {
            $elseStmts = [HydraAst::assignResult($outputKey, Ast::toExpr($prop->undefinedDehydrationValue))];
        }

        return [
            Ast::expr($refProp),
            Ast::if($isInitialized, $thenStmts, $elseStmts),
        ];
    }

    private function assertNotUnitEnum(string $type, string $name): void
    {
        if (enum_exists($type) && !new \ReflectionEnum($type)->isBacked()) {
            throw new \LogicException(
                "Unit enum '{$type}' cannot be used as DTO property '{$name}'. Only backed enums (string/int) are supported."
            );
        }
    }
}
