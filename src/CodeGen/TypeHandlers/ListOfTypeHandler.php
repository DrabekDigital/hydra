<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Stmt;

/**
 * Handles arrays annotated with #[ListOf] attribute.
 */
final class ListOfTypeHandler implements TypeHandler
{
    public function __construct(
        private readonly ClassAnalyzer $analyzer,
    ) {}

    public function priority(): int
    {
        return 60; // Higher than scalar, lower than datetime
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->hasAttribute(ListOf::class);
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);
        $attr = $property->getAttribute(ListOf::class);
        if (!$attr instanceof ListOf) {
            throw new \InvalidArgumentException("Missing ListOf attribute for '{$name}'");
        }
        $itemType = $attr->value;
        $isObjectType = $this->analyzer->isObjectType($itemType);

        if ($isObjectType) {
            $context->registerNestedType($itemType);
        }
        if ($attr->mapping !== null) {
            foreach ($attr->mapping as $mappedClass) {
                $context->registerNestedType($mappedClass);
            }
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $itemType, $isObjectType, $attr);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path, $itemType, $isObjectType, $attr);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path, $itemType, $isObjectType, $attr);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $itemType, $isObjectType, $attr);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $attr = $property->getAttribute(ListOf::class);
        if (!$attr instanceof ListOf) {
            throw new \InvalidArgumentException("Missing ListOf attribute for '{$name}'");
        }
        $itemType = $attr->value;
        $isObjectType = $this->analyzer->isObjectType($itemType);

        if ($isObjectType) {
            $context->registerNestedType($itemType);
        }
        if ($attr->mapping !== null) {
            foreach ($attr->mapping as $mappedClass) {
                $context->registerNestedType($mappedClass);
            }
        }

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $itemType, $isObjectType, $context, $attr);
        }

        if ($property->isNullable) {
            return $this->generateNullableDehydration($name, $outputKey, $itemType, $isObjectType, $context, $attr);
        }

        return $this->generateSimpleDehydration($name, $outputKey, $itemType, $isObjectType, $context, $attr);
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array<int, Stmt>
     */
    private function generateLoopBody(string $name, string $itemType, bool $isObjectType, ListOf $attr): array
    {
        if ($isObjectType) {
            if ($attr->discriminator !== null && $attr->mapping !== null) {
                $disc = $attr->discriminator;
                $itemPath = Ast::funcCall('sprintf', [
                    Ast::string('%s[%d]'),
                    Ast::var("itemPath_{$name}"),
                    Ast::var("i_{$name}"),
                ]);
                $switchCases = [];
                foreach ($attr->mapping as $discValue => $mappedClass) {
                    $switchCases[] = new Stmt\Case_(
                        Ast::string($discValue),
                        [
                            HydraAst::wrapInHydrationCatch([Ast::expr(Ast::assign(
                                Ast::arrayAccess(Ast::var("result_{$name}"), null),
                                Ast::methodCall(
                                    Ast::arrayAccess(Ast::property(Ast::var('this'), 'nestedHydrators'), $mappedClass),
                                    'hydrate',
                                    [Ast::var("itemPayload_{$name}"), $itemPath]
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
                            $itemPath
                        ),
                        new Stmt\Break_(),
                    ]
                );

                return [
                    Ast::if(
                        Ast::not(Ast::funcCall('is_array', [Ast::var('item')])),
                        [HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(Ast::string("Expected array item for '{$name}', got "), Ast::funcCall('gettype', [Ast::var('item')])),
                            $name,
                            $itemPath
                        )],
                        [
                            Ast::expr(Ast::assign(Ast::var("itemPayload_{$name}"), Ast::var('item'))),
                            Ast::if(
                                Ast::not(Ast::funcCall('array_key_exists', [Ast::string($disc), Ast::var("itemPayload_{$name}")])),
                                [HydraAst::appendHydrationError(
                                    'MISSING_KEY',
                                    Ast::string("Missing discriminator '{$disc}'"),
                                    $disc,
                                    $itemPath
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

            // $result_{name}[] = $this->nestedHydrators['Type']->hydrate($item, "$path[$i]")
            return [
                HydraAst::wrapInHydrationCatch([Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var("result_{$name}"), null),
                    Ast::methodCall(
                        Ast::arrayAccess(
                            Ast::property(Ast::var('this'), 'nestedHydrators'),
                            $itemType
                        ),
                        'hydrate',
                        [
                            Ast::var('item'),
                            Ast::concat(Ast::var("itemPath_{$name}"), Ast::string("[\$i_{$name}]")),
                        ]
                    )
                ))]),
            ];
        }

        $itemPath = Ast::funcCall('sprintf', [
            Ast::string('%s[%d]'),
            Ast::var("itemPath_{$name}"),
            Ast::var("i_{$name}"),
        ]);
        $check = HydraAst::scalarTypeCheck(Ast::var('item'), $itemType);
        if ($check === null) {
            return [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var("result_{$name}"), null),
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
                        Ast::string("Expected {$itemType} item for '{$name}', got "),
                        Ast::funcCall('gettype', [Ast::var('item')])
                    ),
                    Ast::var("i_{$name}"),
                    $itemPath
                )],
                [
                    Ast::expr(Ast::assign(
                        Ast::arrayAccess(Ast::var("result_{$name}"), null),
                        Ast::var('item')
                    )),
                ]
            ),
        ];
    }

    /**
     * @return array<int, Stmt>
     */
    private function generateDehydrateLoop(string $name, string $outputKey, string $itemType, bool $isObjectType, Expr $pathExpr, ListOf $attr): array
    {
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
                                        [Ast::var('item'), $pathExpr]
                                    )
                                )),
                                Ast::expr(Ast::assign(
                                    Ast::arrayAccess(Ast::var("payload_{$name}"), $disc),
                                    Ast::string($discValue)
                                )),
                                Ast::expr(Ast::assign(
                                    Ast::arrayAccess(Ast::var("result_{$name}"), null),
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
                            Ast::expr(Ast::assign(Ast::var("class_{$name}"), Ast::funcCall('get_class', [Ast::var('item')]))),
                            new Stmt\Switch_(Ast::var("class_{$name}"), $switchCases),
                        ],
                        null
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
                            Ast::arrayAccess(Ast::var("result_{$name}"), null),
                            Ast::methodCall(
                                Ast::arrayAccess(
                                    Ast::property(Ast::var('this'), 'nestedDehydrators'),
                                    $itemType
                                ),
                                'dehydrate',
                                [Ast::var('item'), $pathExpr]
                            )
                        ))]),
                    ],
                    null
                ),
                HydraAst::assignResult($outputKey, Ast::var("result_{$name}")),
            ];
        }

        $check = HydraAst::scalarTypeCheck(Ast::var('item'), $itemType);
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
                    $pathExpr
                )],
                [
                    Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                    Ast::expr(Ast::assign(Ast::var("i_{$name}"), Ast::int(0))),
                    Ast::foreach(
                        HydraAst::objectProperty($name),
                        Ast::var('item'),
                        [
                            Ast::if(
                                Ast::not($check),
                                [HydraAst::appendDehydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected {$itemType} item for '{$name}', got "),
                                        Ast::funcCall('gettype', [Ast::var('item')])
                                    ),
                                    Ast::var("i_{$name}"),
                                    Ast::funcCall('sprintf', [Ast::string('%s[%d]'), $pathExpr, Ast::var("i_{$name}")])
                                )],
                                [
                                    Ast::expr(Ast::assign(
                                        Ast::arrayAccess(Ast::var("result_{$name}"), null),
                                        Ast::var('item')
                                    )),
                                ]
                            ),
                            Ast::expr(new PostInc(Ast::var("i_{$name}"))),
                        ],
                        null
                    ),
                    HydraAst::assignResult($outputKey, Ast::var("result_{$name}")),
                ]
            ),
        ];
    }

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path, string $itemType, bool $isObjectType, ListOf $attr): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        $elseStmts = [
            Ast::if(
                Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                [
                    HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(
                            Ast::string("Expected array for '{$name}', got "),
                            Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    ),
                ],
                [
                    Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                    Ast::expr(Ast::assign(Ast::var("i_{$name}"), Ast::int(0))),
                    Ast::expr(Ast::assign(Ast::var("itemPath_{$name}"), $path)),
                    Ast::foreach(
                        HydraAst::dataAccess($key),
                        Ast::var('item'),
                        [
                            ...$this->generateLoopBody($name, $itemType, $isObjectType, $attr),
                            Ast::expr(new PostInc(Ast::var("i_{$name}"))),
                        ],
                        null
                    ),
                    HydraAst::assignArg($name, Ast::var("result_{$name}")),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path, string $itemType, bool $isObjectType, ListOf $attr): array
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
                        Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                        [
                            HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(
                                    Ast::string("Expected array or null for '{$name}', got "),
                                    Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            ),
                        ],
                        [
                            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
                            Ast::expr(Ast::assign(Ast::var("i_{$name}"), Ast::int(0))),
                            Ast::expr(Ast::assign(Ast::var("itemPath_{$name}"), $path)),
                            Ast::foreach(
                                HydraAst::dataAccess($key),
                                Ast::var('item'),
                                [
                                    ...$this->generateLoopBody($name, $itemType, $isObjectType, $attr),
                                    Ast::expr(new PostInc(Ast::var("i_{$name}"))),
                                ],
                                null
                            ),
                            HydraAst::assignArg($name, Ast::var("result_{$name}")),
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
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $itemType, bool $isObjectType, ListOf $attr): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        $processStmts = [
            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var("i_{$name}"), Ast::int(0))),
            Ast::expr(Ast::assign(Ast::var("itemPath_{$name}"), $path)),
            Ast::foreach(
                HydraAst::dataAccess($key),
                Ast::var('item'),
                [
                    ...$this->generateLoopBody($name, $itemType, $isObjectType, $attr),
                    Ast::expr(new PostInc(Ast::var("i_{$name}"))),
                ],
                null
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected array or null for '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $processStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected array for '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
                    $processStmts
                ),
            ];
        }

        $elseStmts = [HydraAst::assignArg($name, $default)];

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $itemType, bool $isObjectType, ListOf $attr): array
    {
        $processStmts = [
            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var("i_{$name}"), Ast::int(0))),
            Ast::expr(Ast::assign(Ast::var("itemPath_{$name}"), $path)),
            Ast::foreach(
                HydraAst::dataAccess($key),
                Ast::var('item'),
                [
                    ...$this->generateLoopBody($name, $itemType, $isObjectType, $attr),
                    Ast::expr(new PostInc(Ast::var("i_{$name}"))),
                ],
                null
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected array or null for '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $processStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected array for '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
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
     * @return Stmt[]
     */
    private function generateSimpleDehydration(string $name, string $outputKey, string $itemType, bool $isObjectType, GenerationContext $ctx, ListOf $attr): array
    {
        $path = HydraAst::pathExpr($name);

        return $this->generateDehydrateLoop($name, $outputKey, $itemType, $isObjectType, $path, $attr);
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableDehydration(string $name, string $outputKey, string $itemType, bool $isObjectType, GenerationContext $ctx, ListOf $attr): array
    {
        $path = HydraAst::pathExpr($name);

        return [
            Ast::if(
                Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                [HydraAst::assignResult($outputKey, Ast::null())],
                $this->generateDehydrateLoop($name, $outputKey, $itemType, $isObjectType, $path, $attr)
            ),
        ];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, string $itemType, bool $isObjectType, GenerationContext $ctx, ListOf $attr): array
    {
        $path = HydraAst::pathExpr($name);

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
                    $this->generateDehydrateLoop($name, $outputKey, $itemType, $isObjectType, $path, $attr)
                ),
            ];
        } else {
            $thenStmts = $this->generateDehydrateLoop($name, $outputKey, $itemType, $isObjectType, $path, $attr);
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
