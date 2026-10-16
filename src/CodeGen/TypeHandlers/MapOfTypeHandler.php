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
    private const SUPPORTED_KEY_TYPES = ['string', 'int'];
    private const SUPPORTED_VALUE_SCALAR_TYPES = ['string', 'int', 'float', 'bool', 'array', 'mixed', 'null'];

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
        $this->validateMapKeyType($attr->key, $name);
        $valueType = $attr->value;
        $isObjectType = $this->resolveObjectValueTypeMode($valueType, $attr, $name);

        if ($isObjectType) {
            if (class_exists($valueType)) {
                $context->registerNestedType($valueType);
            }
        }
        if ($attr->mapping !== null) {
            foreach ($attr->mapping as $mappedClass) {
                $context->registerNestedType($mappedClass);
            }
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $valueType, $isObjectType, $attr);
        }
        if ($property->isRequired()) {
            return $this->generateRequired($key, $name, $path, $valueType, $isObjectType, $attr);
        }
        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($key, $name, $path, $valueType, $isObjectType, $attr);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $valueType, $isObjectType, $attr);
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
        $this->validateMapKeyType($attr->key, $name);
        $valueType = $attr->value;
        $isObjectType = $this->resolveObjectValueTypeMode($valueType, $attr, $name);

        if ($isObjectType) {
            if (class_exists($valueType)) {
                $context->registerNestedType($valueType);
            }
        }
        if ($attr->mapping !== null) {
            foreach ($attr->mapping as $mappedClass) {
                $context->registerNestedType($mappedClass);
            }
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $valueType, $isObjectType, $context, $attr);
        }
        if ($property->isNullable) {
            return $this->generateNullableDehydration($name, $outputKey, $valueType, $isObjectType, $attr);
        }

        return $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType, $attr);
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @return list<Stmt>
     */
    private function generateLoopBody(string $name, string $valueType, bool $isObjectType, MapOf $attr): array
    {
        if ($isObjectType) {
            if ($attr->discriminator !== null && $attr->mapping !== null) {
                $disc = $attr->discriminator;
                $switchCases = [];
                foreach ($attr->mapping as $discValue => $mappedClass) {
                    $switchCases[] = new Stmt\Case_(
                        Ast::string($discValue),
                        [
                            HydraAst::wrapInHydrationCatch([Ast::expr(Ast::assign(
                                Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                                Ast::methodCall(
                                    Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedHydrators'), $mappedClass),
                                    'hydrate',
                                    [Ast::var("itemPayload_{$name}"), Ast::var("itemPath_{$name}")]
                                )
                            ))]),
                            new Stmt\Break_(),
                        ]
                    );
                }
                $switchCases[] = new Stmt\Case_(
                    null,
                    [
                        HydraAst::appendHydrationError(
                            'INVALID_VALUE',
                            Ast::concat(
                                Ast::string("Unknown discriminator value for '{$disc}': "),
                                Ast::funcCall('json_encode', [Ast::var("discValue_{$name}")])
                            ),
                            $disc,
                            Ast::var("itemPath_{$name}")
                        ),
                        new Stmt\Break_(),
                    ]
                );

                return [
                    Ast::expr(Ast::assign(
                        Ast::var("itemPath_{$name}"),
                        Ast::concat(Ast::var("mapPath_{$name}"), Ast::string('.'), Ast::var("mapKey_{$name}"))
                    )),
                    Ast::if(
                        Ast::not(Ast::funcCall('is_array', [Ast::var('item')])),
                        [HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(Ast::string("Expected array value for '{$name}', got "), Ast::funcCall('gettype', [Ast::var('item')])),
                            Ast::var("mapKey_{$name}"),
                            Ast::var("itemPath_{$name}")
                        )],
                        [
                            Ast::expr(Ast::assign(Ast::var("itemPayload_{$name}"), Ast::var('item'))),
                            Ast::if(
                                Ast::not(Ast::funcCall('array_key_exists', [Ast::string($disc), Ast::var("itemPayload_{$name}")])),
                                [HydraAst::appendHydrationError(
                                    'MISSING_KEY',
                                    Ast::string("Missing discriminator '{$disc}'"),
                                    $disc,
                                    Ast::var("itemPath_{$name}")
                                )],
                                [
                                    Ast::expr(Ast::assign(
                                        Ast::var("discValue_{$name}"),
                                        Ast::arrayAccess(Ast::var("itemPayload_{$name}"), $disc)
                                    )),
                                    ...($attr->passDiscriminator ? [] : [
                                        new Stmt\Unset_([Ast::arrayAccess(Ast::var("itemPayload_{$name}"), $disc)]),
                                    ]),
                                    new Stmt\Switch_(Ast::var("discValue_{$name}"), $switchCases),
                                ]
                            ),
                        ]
                    ),
                ];
            }

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

        $check = HydraAst::scalarTypeCheck(Ast::var('item'), $valueType, acceptIntAsFloat: true);
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
                        HydraAst::widenIntToFloat(Ast::var('item'), [$valueType])
                    )),
                ]
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateProcessStmts(string $name, string $key, string $valueType, bool $isObjectType, Expr $path, MapOf $attr): array
    {
        return [
            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var("mapPath_{$name}"), $path)),
            Ast::foreach(
                HydraAst::dataAccess($key),
                Ast::var('item'),
                $this->generateLoopBody($name, $valueType, $isObjectType, $attr),
                Ast::var("mapKey_{$name}")
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateRequired(string $key, string $name, Expr $path, string $valueType, bool $isObjectType, MapOf $attr): array
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
                    $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path, $attr)
                ),
            ]
        )];
    }

    /**
     * @return list<Stmt>
     */
    private function generateNullableRequiredKey(string $key, string $name, Expr $path, string $valueType, bool $isObjectType, MapOf $attr): array
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
                            $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path, $attr)
                        ),
                    ]
                ),
            ]
        )];
    }

    /**
     * @return list<Stmt>
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $valueType, bool $isObjectType, MapOf $attr): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());
        $processStmts = $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path, $attr);

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
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $valueType, bool $isObjectType, MapOf $attr): array
    {
        $processStmts = $this->generateProcessStmts($name, $key, $valueType, $isObjectType, $path, $attr);

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
    private function generateDehydrateLoop(string $name, string $outputKey, string $valueType, bool $isObjectType, MapOf $attr): array
    {
        $path = HydraAst::pathExpr($name);

        if ($isObjectType) {
            if ($attr->discriminator !== null && $attr->mapping !== null) {
                $disc = $attr->discriminator;
                $switchCases = [];
                foreach ($attr->mapping as $discValue => $mappedClass) {
                    $switchCases[] = new Stmt\Case_(
                        Ast::classConst($mappedClass, 'class'),
                        [
                            HydraAst::wrapInDehydrationCatch([
                                Ast::expr(Ast::assign(
                                    Ast::var("payload_{$name}"),
                                    Ast::methodCall(
                                        Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedDehydrators'), $mappedClass),
                                        'dehydrate',
                                        [Ast::var('item'), Ast::var("itemPath_{$name}")]
                                    )
                                )),
                                Ast::expr(Ast::assign(Ast::arrayAccess(Ast::var("payload_{$name}"), $disc), Ast::string($discValue))),
                                Ast::expr(Ast::assign(
                                    Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                                    Ast::var("payload_{$name}")
                                )),
                            ]),
                            new Stmt\Break_(),
                        ]
                    );
                }
                $switchCases[] = new Stmt\Case_(
                    null,
                    [
                        Ast::throw(Ast::new('\InvalidArgumentException', [
                            Ast::concat(Ast::string("Unsupported mapped class in '{$name}': "), Ast::var("class_{$name}")),
                        ])),
                    ]
                );

                return [
                    Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                    Ast::foreach(
                        HydraAst::objectProperty($name),
                        Ast::var('item'),
                        [
                            Ast::expr(Ast::assign(
                                Ast::var("itemPath_{$name}"),
                                Ast::concat($path, Ast::string('.'), Ast::var("mapKey_{$name}"))
                            )),
                            Ast::expr(Ast::assign(Ast::var("class_{$name}"), Ast::funcCall('get_class', [Ast::var('item')]))),
                            new Stmt\Switch_(Ast::var("class_{$name}"), $switchCases),
                        ],
                        Ast::var("mapKey_{$name}")
                    ),
                    HydraAst::assignResult($outputKey, Ast::var("result_{$name}")),
                ];
            }

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

        $check = HydraAst::scalarTypeCheck(Ast::var('item'), $valueType);
        if ($check === null) {
            return [HydraAst::assignResult($outputKey, HydraAst::objectProperty($name))];
        }

        return [
            Ast::if(
                Ast::not(Ast::funcCall('is_array', [HydraAst::objectProperty($name)])),
                [HydraAst::appendDehydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(
                        Ast::string("Expected array for '{$name}', got "),
                        Ast::funcCall('gettype', [HydraAst::objectProperty($name)])
                    ),
                    $name,
                    $path
                )],
                [
                    Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                    Ast::foreach(
                        HydraAst::objectProperty($name),
                        Ast::var('item'),
                        [
                            Ast::if(
                                Ast::not($check),
                                [HydraAst::appendDehydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected {$valueType} value for '{$name}', got "),
                                        Ast::funcCall('gettype', [Ast::var('item')])
                                    ),
                                    Ast::var("mapKey_{$name}"),
                                    Ast::funcCall('sprintf', [Ast::string('%s.%s'), $path, Ast::var("mapKey_{$name}")])
                                )],
                                [
                                    Ast::expr(Ast::assign(
                                        Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var("mapKey_{$name}")),
                                        Ast::var('item')
                                    )),
                                ]
                            ),
                        ],
                        Ast::var("mapKey_{$name}")
                    ),
                    HydraAst::assignResult($outputKey, Ast::var("result_{$name}")),
                ]
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateNullableDehydration(string $name, string $outputKey, string $valueType, bool $isObjectType, MapOf $attr): array
    {
        return [
            Ast::if(
                Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                [HydraAst::assignResult($outputKey, Ast::null())],
                $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType, $attr)
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, string $valueType, bool $isObjectType, GenerationContext $ctx, MapOf $attr): array
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
                    $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType, $attr)
                ),
            ];
        } else {
            $thenStmts = $this->generateDehydrateLoop($name, $outputKey, $valueType, $isObjectType, $attr);
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

    private function resolveObjectValueTypeMode(string $valueType, MapOf $attr, string $name): bool
    {
        $hasDiscriminator = $attr->discriminator !== null;
        $hasMapping = $attr->mapping !== null;

        if ($hasDiscriminator xor $hasMapping) {
            throw new \InvalidArgumentException(
                "Invalid MapOf configuration for '{$name}': discriminator and mapping must be set together."
            );
        }

        $isSupportedScalarType = in_array($valueType, self::SUPPORTED_VALUE_SCALAR_TYPES, true);
        $isClassObjectType = $this->analyzer->isObjectType($valueType);
        $isInterfaceType = interface_exists($valueType);
        if (!$isSupportedScalarType && !$isClassObjectType && !$isInterfaceType) {
            throw new \InvalidArgumentException(
                "Invalid MapOf configuration for '{$name}': unsupported value type '{$valueType}'. "
                .'Use supported scalar type, class, or interface.'
            );
        }

        if ($hasDiscriminator && !$isClassObjectType && !$isInterfaceType) {
            throw new \InvalidArgumentException(
                "Invalid MapOf configuration for '{$name}': discriminator and mapping require class or interface value type."
            );
        }

        if ($isInterfaceType && !$hasDiscriminator) {
            throw new \InvalidArgumentException(
                "Invalid MapOf configuration for '{$name}': interface value type '{$valueType}' requires discriminator and mapping."
            );
        }

        if ($hasMapping) {
            foreach ($attr->mapping as $discValue => $mappedClass) {
                if (!class_exists($mappedClass)) {
                    throw new \InvalidArgumentException(
                        "Invalid MapOf configuration for '{$name}': mapped class '{$mappedClass}' for discriminator '{$discValue}' does not exist."
                    );
                }

                if (!is_a($mappedClass, $valueType, true)) {
                    throw new \InvalidArgumentException(
                        "Invalid MapOf configuration for '{$name}': mapped class '{$mappedClass}' must be subtype of '{$valueType}'."
                    );
                }
            }
        }

        return $isClassObjectType || $isInterfaceType;
    }

    private function validateMapKeyType(string $keyType, string $name): void
    {
        if (!in_array($keyType, self::SUPPORTED_KEY_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Invalid MapOf configuration for '{$name}': unsupported key type '{$keyType}'. Supported key types are string and int."
            );
        }
    }
}
