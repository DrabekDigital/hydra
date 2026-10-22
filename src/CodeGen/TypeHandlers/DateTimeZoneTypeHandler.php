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
 * Handles DateTimeZone type: hydrates from a timezone name string, dehydrates to getName().
 */
final class DateTimeZoneTypeHandler implements TypeHandler
{
    public function priority(): int
    {
        return 76; // Just above DateTime (75)
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->getPrimaryType() === \DateTimeZone::class
            || $property->getPrimaryType() === 'DateTimeZone';
    }

    /** @return Stmt[] */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path);
        }
        if ($property->isRequired()) {
            return $this->generateRequired($key, $name, $path);
        }
        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($key, $name, $path);
        }

        return $this->generateWithDefault($property, $key, $name, $path);
    }

    /** @return Stmt[] */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $context);
        }

        if ($property->isNullable) {
            return [
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        Ast::methodCall(HydraAst::objectProperty($name), 'getName'),
                        Ast::null()
                    )
                ),
            ];
        }

        return [
            HydraAst::assignResult(
                $outputKey,
                Ast::methodCall(HydraAst::objectProperty($name), 'getName')
            ),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration helpers
    // ─────────────────────────────────────────────────────────────

    /** @return list<Stmt> */
    private function parseStmts(string $name, string $key, Expr $path): array
    {
        return [
            Ast::tryCatch(
                [HydraAst::assignArg($name, Ast::new('\DateTimeZone', [HydraAst::dataAccess($key)]))],
                [Ast::catch_(['\Throwable'], 'e', [
                    HydraAst::appendHydrationError(
                        'INVALID_VALUE',
                        Ast::concat(
                            Ast::string("Invalid timezone for '{$name}': "),
                            Ast::methodCall(Ast::var('e'), 'getMessage')
                        ),
                        $name,
                        $path
                    ),
                ])]
            ),
        ];
    }

    /** @return Stmt[] */
    private function generateRequired(string $key, string $name, Expr $path): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
            [Ast::if(
                Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(Ast::string("Expected string for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                    $name,
                    $path
                )],
                $this->parseStmts($name, $key, $path)
            )]
        )];
    }

    /** @return Stmt[] */
    private function generateNullableRequiredKey(string $key, string $name, Expr $path): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
            [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [Ast::if(
                    Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected string or null for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $this->parseStmts($name, $key, $path)
                )]
            )]
        )];
    }

    /** @return Stmt[] */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        if ($prop->isNullable) {
            $thenStmts = [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [Ast::if(
                    Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected string or null for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $this->parseStmts($name, $key, $path)
                )]
            )];
        } else {
            $thenStmts = [Ast::if(
                Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(Ast::string("Expected string for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                    $name,
                    $path
                )],
                $this->parseStmts($name, $key, $path)
            )];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, [HydraAst::assignArg($name, $default)])];
    }

    /** @return Stmt[] */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        if ($prop->isNullable) {
            $thenStmts = [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [Ast::if(
                    Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                    [HydraAst::appendHydrationError(
                        'TYPE_MISMATCH',
                        Ast::concat(Ast::string("Expected string or null for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                        $name,
                        $path
                    )],
                    $this->parseStmts($name, $key, $path)
                )]
            )];
        } else {
            $thenStmts = [Ast::if(
                Ast::not(Ast::funcCall('is_string', [HydraAst::dataAccess($key)])),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(Ast::string("Expected string for DateTimeZone '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                    $name,
                    $path
                )],
                $this->parseStmts($name, $key, $path)
            )];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Dehydration helpers
    // ─────────────────────────────────────────────────────────────

    /** @return Stmt[] */
    private function generateUndefinableDehydration(PropertyMetadata $prop, string $name, string $outputKey, GenerationContext $ctx): array
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
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        Ast::methodCall(HydraAst::objectProperty($name), 'getName'),
                        Ast::null()
                    )
                ),
            ];
        } else {
            $thenStmts = [
                HydraAst::assignResult(
                    $outputKey,
                    Ast::methodCall(HydraAst::objectProperty($name), 'getName')
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
