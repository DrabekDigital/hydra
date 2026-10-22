<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles properties with custom mapper attributes.
 */
final class CustomMapperTypeHandler implements TypeHandler
{
    public function priority(): int
    {
        return 200; // Highest priority - custom mappers override everything
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $this->getMapperAttribute($property) !== null;
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        $mapperAttr = $this->getMapperAttribute($property);
        if ($mapperAttr === null) {
            throw new \InvalidArgumentException("Missing mapper attribute for '{$name}'");
        }
        $mapperClass = $mapperAttr->getMapperClass();

        // Register mapper class
        $context->registerNestedType($mapperClass);

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $mapperClass);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path, $mapperClass);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path, $mapperClass);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $mapperClass);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $context->markDehydrationErrorsGenerated();

        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $path = HydraAst::pathExpr($name);

        $mapperAttr = $this->getMapperAttribute($property);
        if ($mapperAttr === null) {
            throw new \InvalidArgumentException("Missing mapper attribute for '{$name}'");
        }
        $mapperClass = $mapperAttr->getMapperClass();

        // Register mapper class
        $context->registerNestedType($mapperClass);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $path, $mapperClass, $context);
        }

        if ($property->isNullable) {
            return $this->generateNullableDehydration($name, $outputKey, $path, $mapperClass);
        }

        // Simple case
        return [
            $this->wrapInMapperCatch(
                HydraAst::assignResult(
                    $outputKey,
                    $this->mapperDehydrateCall($mapperClass, $name, $outputKey, $path)
                ),
                $outputKey,
                $path
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function getMapperAttribute(PropertyMetadata $property): ?MapperAttribute
    {
        foreach ($property->attributes as $attr) {
            if ($attr instanceof MapperAttribute) {
                return $attr;
            }
        }

        return null;
    }

    private function mapperHydrateCall(string $mapperClass, string $key, Expr $path): Expr\MethodCall
    {
        return Ast::methodCall(
            Ast::arrayAccess(
                Ast::property(Ast::var('this'), 'customMappers'),
                $mapperClass
            ),
            'hydrate',
            [HydraAst::dataAccess($key), Ast::string($key), $path]
        );
    }

    private function mapperDehydrateCall(string $mapperClass, string $name, string $outputKey, Expr $path): Expr\MethodCall
    {
        return Ast::methodCall(
            Ast::arrayAccess(
                Ast::property(Ast::var('this'), 'customMappers'),
                $mapperClass
            ),
            'dehydrate',
            [HydraAst::objectProperty($name), Ast::string($outputKey), $path]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Generators
    // ─────────────────────────────────────────────────────────────

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path, string $mapperClass): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        $elseStmts = [
            $this->wrapInMapperHydrationCatch(
                HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                $name,
                $path
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path, string $mapperClass): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path),
        ];

        $elseStmts = [
            Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [
                    $this->wrapInMapperHydrationCatch(
                        HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                        $name,
                        $path
                    ),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $mapperClass): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        $this->wrapInMapperHydrationCatch(
                            HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                            $name,
                            $path
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                $this->wrapInMapperHydrationCatch(
                    HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                    $name,
                    $path
                ),
            ];
        }

        $elseStmts = [HydraAst::assignArg($name, $default)];

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $mapperClass): array
    {
        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        $this->wrapInMapperHydrationCatch(
                            HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                            $name,
                            $path
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                $this->wrapInMapperHydrationCatch(
                    HydraAst::assignArg($name, $this->mapperHydrateCall($mapperClass, $key, $path)),
                    $name,
                    $path
                ),
            ];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Dehydration Generators
    // ─────────────────────────────────────────────────────────────

    private function wrapInMapperHydrationCatch(Stmt\Expression $stmt, string $name, Expr $path): Stmt\TryCatch
    {
        $defaultErrorStmt = HydraAst::appendHydrationError(
            'INVALID_VALUE',
            Ast::methodCall(Ast::var('e'), 'getMessage'),
            $name,
            $path
        );

        $mapperErrorVar = Ast::var('mapperError');
        $mapperErrorAssign = Ast::expr(Ast::assign(
            $mapperErrorVar,
            Ast::methodCall(Ast::var('e'), 'getHydrationError')
        ));
        $appendMapperErrorStmt = Ast::expr(Ast::assign(
            Ast::arrayAccess(Ast::var('errors'), null),
            $mapperErrorVar
        ));
        $mapperErrorBranch = Ast::if(
            Ast::notIdentical($mapperErrorVar, Ast::null()),
            [$appendMapperErrorStmt],
            [$defaultErrorStmt]
        );

        return Ast::tryCatch(
            [$stmt],
            [
                Ast::catch_(['\DrabekDigital\Hydra\Exceptions\MapperException'], 'e', [
                    $mapperErrorAssign,
                    $mapperErrorBranch,
                ]),
                Ast::catch_(['\Throwable'], 'e', [$defaultErrorStmt]),
            ]
        );
    }

    private function wrapInMapperCatch(Stmt\Expression $stmt, string $outputKey, Expr $path): Stmt\TryCatch
    {
        $defaultErrorStmt = HydraAst::appendDehydrationError(
            'INVALID_VALUE',
            Ast::methodCall(Ast::var('e'), 'getMessage'),
            $outputKey,
            $path
        );

        $mapperErrorVar = Ast::var('mapperError');
        $mapperErrorAssign = Ast::expr(Ast::assign(
            $mapperErrorVar,
            Ast::methodCall(Ast::var('e'), 'getDehydrationError')
        ));
        $appendMapperErrorStmt = Ast::expr(Ast::assign(
            Ast::arrayAccess(Ast::var('errors'), null),
            $mapperErrorVar
        ));
        $mapperErrorBranch = Ast::if(
            Ast::notIdentical($mapperErrorVar, Ast::null()),
            [$appendMapperErrorStmt],
            [$defaultErrorStmt]
        );

        return Ast::tryCatch(
            [$stmt],
            [
                Ast::catch_(['\DrabekDigital\Hydra\Exceptions\MapperException'], 'e', [
                    $mapperErrorAssign,
                    $mapperErrorBranch,
                ]),
                Ast::catch_(['\Throwable'], 'e', [$defaultErrorStmt]),
            ]
        );
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableDehydration(string $name, string $outputKey, Expr $path, string $mapperClass): array
    {
        return [
            $this->wrapInMapperCatch(
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        $this->mapperDehydrateCall($mapperClass, $name, $outputKey, $path),
                        Ast::null()
                    )
                ),
                $outputKey,
                $path
            ),
        ];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, Expr $path, string $mapperClass, GenerationContext $ctx): array
    {
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($ctx->className, 'class'),
                Ast::string($name),
            ])
        );

        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);

        if ($prop->isNullable) {
            $thenStmts = [
                $this->wrapInMapperCatch(
                    HydraAst::assignResult(
                        $outputKey,
                        Ast::ternary(
                            Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                            $this->mapperDehydrateCall($mapperClass, $name, $outputKey, $path),
                            Ast::null()
                        )
                    ),
                    $outputKey,
                    $path
                ),
            ];
        } else {
            $thenStmts = [
                $this->wrapInMapperCatch(
                    HydraAst::assignResult($outputKey, $this->mapperDehydrateCall($mapperClass, $name, $outputKey, $path)),
                    $outputKey,
                    $path
                ),
            ];
        }

        $elseStmts = null;
        if ($prop->shouldEmitUndefinedDehydrationValue()) {
            $elseStmts = [HydraAst::assignResult($outputKey, Ast::toExpr($prop->undefinedDehydrationValue))];
        }

        return [
            Ast::expr($refProp),
            Ast::if($isInitialized, $thenStmts, $elseStmts),
        ];
    }
}
