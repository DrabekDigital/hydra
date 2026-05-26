<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles BackedEnum types.
 */
final class BackedEnumTypeHandler implements TypeHandler
{
    public function priority(): int
    {
        return 100; // Higher than object handler
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        $primaryType = $property->getPrimaryType();
        if ($primaryType === null) {
            return false;
        }

        if (!enum_exists($primaryType)) {
            return false;
        }

        $reflection = new \ReflectionEnum($primaryType);

        return $reflection->isBacked();
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);
        $enumClass = $property->getPrimaryType();
        if ($enumClass === null) {
            throw new \InvalidArgumentException("Enum type is missing for property '{$name}'");
        }
        $shortEnum = GenerationContext::getShortName($enumClass);
        $backingType = $this->getBackingType($enumClass);

        // Register enum type for use statement
        $context->registerNestedType($enumClass);

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $shortEnum, $backingType);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path, $shortEnum, $backingType);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path, $shortEnum, $backingType);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $shortEnum, $backingType);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $enumClass = $property->getPrimaryType();
        if ($enumClass === null) {
            throw new \InvalidArgumentException("Enum type is missing for property '{$name}'");
        }

        // Register enum type for use statement
        $context->registerNestedType($enumClass);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $context);
        }

        if ($property->isNullable) {
            // $result['name'] = $object->name?->value
            return [
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        Ast::property(HydraAst::objectProperty($name), 'value'),
                        Ast::null()
                    )
                ),
            ];
        }

        // Simple: $result['name'] = $object->name->value
        return [HydraAst::assignResult($outputKey, Ast::property(HydraAst::objectProperty($name), 'value'))];
    }

    /**
     * Get the expected PHP type for the enum's backing type.
     */
    private function getBackingType(string $enumClass): string
    {
        if (!enum_exists($enumClass)) {
            throw new \InvalidArgumentException("Enum class '{$enumClass}' does not exist");
        }
        $reflection = new \ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType();

        return $backingType?->getName() ?? 'string';
    }

    /**
     * Generate type check condition for enum value.
     */
    private function generateTypeCheck(string $key, string $backingType): Expr
    {
        return Ast::not(Ast::funcCall("is_{$backingType}", [HydraAst::dataAccess($key)]));
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Generators
    // ─────────────────────────────────────────────────────────────

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path, string $shortEnum, string $backingType): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        // Type check + tryFrom and check for null
        $elseStmts = [
            Ast::if(
                $this->generateTypeCheck($key, $backingType),
                [
                    HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(
                            Ast::string("Expected {$backingType} for enum '{$name}', got "),
                            Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    ),
                ],
                [
                    Ast::expr(Ast::assign(
                        Ast::var("enumValue_{$name}"),
                        Ast::staticCall($shortEnum, 'tryFrom', [HydraAst::dataAccess($key)])
                    )),
                    Ast::if(
                        Ast::identical(Ast::var("enumValue_{$name}"), Ast::null()),
                        [
                            HydraAst::appendHydrationError(
                                'INVALID_VALUE',
                                Ast::concat(
                                    Ast::string("Invalid enum value for '{$name}': "),
                                    Ast::funcCall('json_encode', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            ),
                        ],
                        [HydraAst::assignArg($name, Ast::var("enumValue_{$name}"))]
                    ),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path, string $shortEnum, string $backingType): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path),
        ];

        $elseStmts = [
            Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [
                    Ast::if(
                        $this->generateTypeCheck($key, $backingType),
                        [
                            HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(
                                    Ast::string("Expected {$backingType} for enum '{$name}', got "),
                                    Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            ),
                        ],
                        [
                            Ast::expr(Ast::assign(
                                Ast::var("enumValue_{$name}"),
                                Ast::staticCall($shortEnum, 'tryFrom', [HydraAst::dataAccess($key)])
                            )),
                            Ast::if(
                                Ast::identical(Ast::var("enumValue_{$name}"), Ast::null()),
                                [
                                    HydraAst::appendHydrationError(
                                        'INVALID_VALUE',
                                        Ast::concat(
                                            Ast::string("Invalid enum value for '{$name}': "),
                                            Ast::funcCall('json_encode', [HydraAst::dataAccess($key)])
                                        ),
                                        $name,
                                        $path
                                    ),
                                ],
                                [HydraAst::assignArg($name, Ast::var("enumValue_{$name}"))]
                            ),
                        ]
                    ),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $shortEnum, string $backingType): array
    {
        $defaultValue = $prop->getEffectiveDefault();

        // Handle enum default values - generate EnumClass::CaseName
        if ($defaultValue instanceof \BackedEnum) {
            $default = Ast::classConst($shortEnum, $defaultValue->name);
        } else {
            $default = Ast::toExpr($defaultValue);
        }

        $tryFromAndValidate = [
            Ast::expr(Ast::assign(
                Ast::var("enumValue_{$name}"),
                Ast::staticCall($shortEnum, 'tryFrom', [HydraAst::dataAccess($key)])
            )),
            Ast::if(
                Ast::identical(Ast::var("enumValue_{$name}"), Ast::null()),
                [
                    HydraAst::appendHydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Invalid enum value for '{$name}': "),
                            Ast::funcCall('json_encode', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    ),
                ],
                [HydraAst::assignArg($name, Ast::var("enumValue_{$name}"))]
            ),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            $this->generateTypeCheck($key, $backingType),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected {$backingType} for enum '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $tryFromAndValidate
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    $this->generateTypeCheck($key, $backingType),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected {$backingType} for enum '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
                    $tryFromAndValidate
                ),
            ];
        }

        $elseStmts = [HydraAst::assignArg($name, $default)];

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $shortEnum, string $backingType): array
    {
        $tryFromAndValidate = [
            Ast::expr(Ast::assign(
                Ast::var("enumValue_{$name}"),
                Ast::staticCall($shortEnum, 'tryFrom', [HydraAst::dataAccess($key)])
            )),
            Ast::if(
                Ast::identical(Ast::var("enumValue_{$name}"), Ast::null()),
                [
                    HydraAst::appendHydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Invalid enum value for '{$name}': "),
                            Ast::funcCall('json_encode', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    ),
                ],
                [HydraAst::assignArg($name, Ast::var("enumValue_{$name}"))]
            ),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            $this->generateTypeCheck($key, $backingType),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected {$backingType} for enum '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $tryFromAndValidate
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    $this->generateTypeCheck($key, $backingType),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected {$backingType} for enum '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
                    $tryFromAndValidate
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
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, GenerationContext $ctx): array
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
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        Ast::property(HydraAst::objectProperty($name), 'value'),
                        Ast::null()
                    )
                ),
            ];
        } else {
            $thenStmts = [HydraAst::assignResult($outputKey, Ast::property(HydraAst::objectProperty($name), 'value'))];
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
