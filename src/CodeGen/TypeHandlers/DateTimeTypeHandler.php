<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\DateTime as DateTimeAttr;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles DateTimeInterface, DateTime, DateTimeImmutable types.
 */
final class DateTimeTypeHandler implements TypeHandler
{
    private const DATETIME_TYPES = [
        \DateTimeInterface::class,
        \DateTime::class,
        \DateTimeImmutable::class,
    ];

    public function priority(): int
    {
        return 75; // Between enum and object
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        $primaryType = $property->getPrimaryType();
        if ($primaryType === null) {
            return false;
        }

        return in_array($primaryType, self::DATETIME_TYPES, true)
            || is_subclass_of($primaryType, \DateTimeInterface::class);
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        $attr = $property->getAttribute(DateTimeAttr::class) ?? new DateTimeAttr();
        $format = $attr->format;
        $timezone = $attr->timezone;
        $immutable = $attr->immutable;

        $dtClass = $immutable ? '\DateTimeImmutable' : '\DateTime';

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path, $dtClass, $format, $timezone);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path, $dtClass, $format, $timezone);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path, $dtClass, $format, $timezone);
        }

        return $this->generateWithDefault($property, $key, $name, $path, $dtClass, $format, $timezone);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);

        $attr = $property->getAttribute(DateTimeAttr::class) ?? new DateTimeAttr();
        $format = $attr->format;

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $format, $context);
        }

        if ($property->isNullable) {
            // $result['name'] = $object->name?->format($format)
            return [
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        Ast::methodCall(HydraAst::objectProperty($name), 'format', [Ast::string($format)]),
                        Ast::null()
                    )
                ),
            ];
        }

        // Simple: $result['name'] = $object->name->format($format)
        return [
            HydraAst::assignResult(
                $outputKey,
                Ast::methodCall(HydraAst::objectProperty($name), 'format', [Ast::string($format)])
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Helpers
    // ─────────────────────────────────────────────────────────────

    private function createFromFormatExpr(string $dtClass, string $format, ?string $timezone, Expr $dataExpr): Expr\StaticCall
    {
        $tzExpr = $timezone !== null
            ? Ast::new('\DateTimeZone', [Ast::string($timezone)])
            : Ast::new('\DateTimeZone', [Ast::string('UTC')]);

        return Ast::staticCall($dtClass, 'createFromFormat', [Ast::string($format), $dataExpr, $tzExpr]);
    }

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path, string $dtClass, string $format, ?string $timezone): array
    {
        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        $elseStmts = [
            Ast::if(
                Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                [
                    HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(
                            Ast::string("Expected string for DateTime '{$name}', got "),
                            Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                        ),
                        $name,
                        $path
                    ),
                ],
                [
                    Ast::expr(Ast::assign(
                        Ast::var("dt_{$name}"),
                        $this->createFromFormatExpr($dtClass, $format, $timezone, HydraAst::dataAccess($key))
                    )),
                    Ast::if(
                        Ast::identical(Ast::var("dt_{$name}"), Ast::false()),
                        [
                            HydraAst::appendHydrationError(
                                'INVALID_VALUE',
                                Ast::concat(
                                    Ast::string("Invalid DateTime format for '{$name}'. Expected format: {$format}, got: "),
                                    HydraAst::dataAccess($key)
                                ),
                                $name,
                                $path
                            ),
                        ],
                        [HydraAst::assignArg($name, Ast::var("dt_{$name}"))]
                    ),
                ]
            ),
        ];

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path, string $dtClass, string $format, ?string $timezone): array
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
                        Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                        [
                            HydraAst::appendHydrationError(
                                'TYPE_MISMATCH',
                                Ast::concat(
                                    Ast::string("Expected string or null for DateTime '{$name}', got "),
                                    Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                ),
                                $name,
                                $path
                            ),
                        ],
                        [
                            Ast::expr(Ast::assign(
                                Ast::var("dt_{$name}"),
                                $this->createFromFormatExpr($dtClass, $format, $timezone, HydraAst::dataAccess($key))
                            )),
                            Ast::if(
                                Ast::identical(Ast::var("dt_{$name}"), Ast::false()),
                                [
                                    HydraAst::appendHydrationError(
                                        'INVALID_VALUE',
                                        Ast::concat(
                                            Ast::string("Invalid DateTime format for '{$name}'. Expected format: {$format}, got: "),
                                            HydraAst::dataAccess($key)
                                        ),
                                        $name,
                                        $path
                                    ),
                                ],
                                [HydraAst::assignArg($name, Ast::var("dt_{$name}"))]
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
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path, string $dtClass, string $format, ?string $timezone): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        $parseStmts = [
            Ast::expr(Ast::assign(
                Ast::var("dt_{$name}"),
                $this->createFromFormatExpr($dtClass, $format, $timezone, HydraAst::dataAccess($key))
            )),
            Ast::if(
                Ast::identical(Ast::var("dt_{$name}"), Ast::false()),
                [
                    HydraAst::appendHydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Invalid DateTime format for '{$name}'. Expected format: {$format}, got: "),
                            HydraAst::dataAccess($key)
                        ),
                        $name,
                        $path
                    ),
                ],
                [HydraAst::assignArg($name, Ast::var("dt_{$name}"))]
            ),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected string or null for DateTime '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $parseStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected string for DateTime '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
                    $parseStmts
                ),
            ];
        }

        $elseStmts = [HydraAst::assignArg($name, $default)];

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path, string $dtClass, string $format, ?string $timezone): array
    {
        $parseStmts = [
            Ast::expr(Ast::assign(
                Ast::var("dt_{$name}"),
                $this->createFromFormatExpr($dtClass, $format, $timezone, HydraAst::dataAccess($key))
            )),
            Ast::if(
                Ast::identical(Ast::var("dt_{$name}"), Ast::false()),
                [
                    HydraAst::appendHydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Invalid DateTime format for '{$name}'. Expected format: {$format}, got: "),
                            HydraAst::dataAccess($key)
                        ),
                        $name,
                        $path
                    ),
                ],
                [HydraAst::assignArg($name, Ast::var("dt_{$name}"))]
            ),
        ];

        if ($prop->isNullable) {
            $thenStmts = [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    [
                        Ast::if(
                            Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                            [
                                HydraAst::appendHydrationError(
                                    'TYPE_MISMATCH',
                                    Ast::concat(
                                        Ast::string("Expected string or null for DateTime '{$name}', got "),
                                        Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                                    ),
                                    $name,
                                    $path
                                ),
                            ],
                            $parseStmts
                        ),
                    ]
                ),
            ];
        } else {
            $thenStmts = [
                Ast::if(
                    Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                    [
                        HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected string for DateTime '{$name}', got "),
                                Ast::funcCall('gettype', [HydraAst::dataAccess($key)])
                            ),
                            $name,
                            $path
                        ),
                    ],
                    $parseStmts
                ),
            ];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, string $format, GenerationContext $ctx): array
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
                        Ast::methodCall(HydraAst::objectProperty($name), 'format', [Ast::string($format)]),
                        Ast::null()
                    )
                ),
            ];
        } else {
            $thenStmts = [
                HydraAst::assignResult(
                    $outputKey,
                    Ast::methodCall(HydraAst::objectProperty($name), 'format', [Ast::string($format)])
                ),
            ];
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
