<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles arrays annotated with #[MapOf] attribute.
 */
final class MapOfTypeHandler implements TypeHandler
{
    public function __construct(
        private readonly ClassAnalyzer $analyzer,
    ) {}

    public function priority(): int
    {
        return 65; // Between ListOf (60) and DateTime (75)
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->hasAttribute(MapOf::class);
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);
        $attr = $property->getAttribute(MapOf::class);
        if (!$attr instanceof MapOf) {
            throw new \InvalidArgumentException("Missing MapOf attribute for '{$name}'");
        }
        $valueType = $attr->value;
        $isObjectType = $this->analyzer->isObjectType($valueType);

        if ($isObjectType) {
            $context->registerNestedType($valueType);
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $valueType, $isObjectType);
        }
        if ($property->isRequired()) {
            return $this->generateRequired($key, $name, $path, $valueType, $isObjectType);
        }
        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($key, $name, $path, $valueType, $isObjectType);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $valueType, $isObjectType);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $attr = $property->getAttribute(MapOf::class);
        if (!$attr instanceof MapOf) {
            throw new \InvalidArgumentException("Missing MapOf attribute for '{$name}'");
        }
        $valueType = $attr->value;
        $isObjectType = $this->analyzer->isObjectType($valueType);

        if ($isObjectType) {
            $context->registerNestedType($valueType);
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $valueType, $isObjectType, $context);
        }
        if ($property->isNullable) {
            return $this->generateNullableDehydration($name, $outputKey, $valueType, $isObjectType);
        }

        return $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType);
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @return list<Stmt>
     */
    private function generateLoopBody(string $name, string $valueType, bool $isObjectType): array
    {
        if ($isObjectType) {
            return [
                HydraAst::wrapInHydrationCatch([Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                    Ast::methodCall(
                        Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedHydrators'), $valueType),
                        'hydrate',
                        [
                            Ast::var('item'),
                            Ast::concat(Ast::var("mapPath_{$name}"), Ast::string('.'), Ast::var("mapKey_{$name}")),
                        ]
                    )
                ))]),
            ];
        }

        $check = HydraAst::scalarTypeCheck(Ast::var('item'), $valueType);
        if ($check === null) {
            return [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                    Ast::var('item')
                )),
            ];
        }

        return [
            Ast::if(
                Ast::not($check),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(
                        Ast::string("Expected {$valueType} value for '{$name}', got "),
                        Ast::funcCall('gettype', [Ast::var('item')])
                    ),
                    Ast::var("mapKey_{$name}"),
                    Ast::concat(Ast::var("mapPath_{$name}"), Ast::string('.'), Ast::var("mapKey_{$name}"))
                )],
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                        Ast::var('item')
                    )),
                ]
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateProcessStmts(string $name, string $key, string $valueType, bool $isObjectType, Expr $path): array
    {
        return [
            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var("mapPath_{$name}"), $path)),
            Ast::foreach(
                HydraAst::dataAccess($key),
                Ast::var('item'),
                $this->generateLoopBody($name, $valueType, $isObjectType),
                Ast::var("mapKey_{$name}")
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateRequired(string $key, string $name, Expr $path, string $valueType, bool $isObjectType): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
            [
                Ast::if(
                    Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected array for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path)
                ),
            ]
        )];
    }

    /**
     * @return list<Stmt>
     */
    private function generateNullableRequiredKey(string $key, string $name, Expr $path, string $valueType, bool $isObjectType): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
            [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                            [HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(Ast::string("Expected array or null for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                                $name,
                                $path
                            )],
                            $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path)
                        ),
                    ]
                ),
            ]
        )];
    }

    /**
     * @return list<Stmt>
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $valueType, bool $isObjectType): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());
        $processStmts = $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path);

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                            [HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(Ast::string("Expected array or null for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                                $name,
                                $path
                            )],
                            $processStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected array for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $processStmts
                ),
            ];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, [HydraAst::assignArg($name, $default)])];
    }

    /**
     * @return list<Stmt>
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $valueType, bool $isObjectType): array
    {
        $processStmts = $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path);

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                            [HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(Ast::string("Expected array or null for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                                $name,
                                $path
                            )],
                            $processStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected array for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $processStmts
                ),
            ];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Dehydration Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @return list<Stmt>
     */
    private function generateDehydrateLoop(string $name, string $outputKey, string $valueType, bool $isObjectType): array
    {
        if ($isObjectType) {
            $path = HydraAst::pathExpr($name);

            return [
                Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                Ast::foreach(
                    HydraAst::objectProperty($name),
                    Ast::var('item'),
                    [
                        HydraAst::wrapInDehydrationCatch([Ast::expr(Ast::assign(
                            Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                            Ast::methodCall(
                                Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedDehydrators'), $valueType),
                                'dehydrate',
                                [Ast::var('item'), $path]
                            )
                        ))]),
                    ],
                    Ast::var("mapKey_{$name}")
                ),
                HydraAst::assignResult($outputKey, Ast::var("result_{$name}")),
            ];
        }

        return [HydraAst::assignResult($outputKey, HydraAst::objectProperty($name))];
    }

    /**
     * @return list<Stmt>
     */
    private function generateNullableDehydration(string $name, string $outputKey, string $valueType, bool $isObjectType): array
    {
        if ($isObjectType) {
            return [
                Ast::if(
                    Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                    [HydraAst::assignResult($outputKey, Ast::null())],
                    $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType)
                ),
            ];
        }

        return [
            HydraAst::assignResult(
                $outputKey,
                Ast::ternary(
                    Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                    HydraAst::objectProperty($name),
                    Ast::null()
                )
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, string $valueType, bool $isObjectType, GenerationContext $ctx): array
    {
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($ctx->shortName, 'class'),
                Ast::string($name),
            ])
        );

        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                    [HydraAst::assignResult($outputKey, Ast::null())],
                    $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType)
                ),
            ];
        } else {
            $thenStmts = $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType);
        }

        $elseStmts = null;
        if ($prop->undefinableOnDehydrationWhen !== null) {
            $elseStmts = [HydraAst::assignResult($outputKey, Ast::toExpr($prop->undefinableOnDehydrationWhen))];
        }

        return [
            Ast::expr($refProp),
            Ast::if($isInitialized, $thenStmts, $elseStmts),
        ];
    }
}
