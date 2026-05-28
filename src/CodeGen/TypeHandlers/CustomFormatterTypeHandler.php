<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles properties with #[CustomFormatter] — inline closure-based hydrate/dehydrate transforms.
 */
final class CustomFormatterTypeHandler implements TypeHandler
{
    public function priority(): int
    {
        return 190; // Just below CustomMapper (200)
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->getAttribute(CustomFormatter::class) !== null;
    }

    /** @return Stmt[] */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinableHydration($property, $key, $name);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($key, $name, $path);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequired($key, $name, $path);
        }

        return $this->generateWithDefault($property, $key, $name);
    }

    /** @return Stmt[] */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $context->markDehydrationErrorsGenerated();

        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $path = HydraAst::pathExpr($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $context, $path);
        }

        $dehydrated = $this->callDehydrateFormatter($name, HydraAst::objectProperty($name));

        if ($property->isNullable) {
            return [$this->wrapInFormatterCatch(
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        $dehydrated,
                        Ast::null()
                    )
                ),
                $outputKey,
                $path
            )];
        }

        return [$this->wrapInFormatterCatch(HydraAst::assignResult($outputKey, $dehydrated), $outputKey, $path)];
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration generators
    // ─────────────────────────────────────────────────────────────

    /** @return list<Stmt> */
    private function generateRequired(string $key, string $name, Expr $path): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
            [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))]
        )];
    }

    /** @return list<Stmt> */
    private function generateNullableRequired(string $key, string $name, Expr $path): array
    {
        return [Ast::if(
            HydraAst::keyMissing($key),
            [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
            [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))]
            )]
        )];
    }

    /** @return list<Stmt> */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());

        if ($prop->isNullable) {
            $thenStmts = [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))]
            )];
        } else {
            $thenStmts = [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))];
        }

        return [Ast::if(
            HydraAst::keyExists($key),
            $thenStmts,
            [HydraAst::assignArg($name, $default)]
        )];
    }

    /** @return list<Stmt> */
    private function generateUndefinableHydration(PropertyMetadata $prop, string $key, string $name): array
    {
        if ($prop->isNullable) {
            $thenStmts = [Ast::if(
                Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                [HydraAst::assignArg($name, Ast::null())],
                [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))]
            )];
        } else {
            $thenStmts = [HydraAst::assignArg($name, $this->callHydrateFormatter($name, HydraAst::dataAccess($key)))];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Dehydration generators
    // ─────────────────────────────────────────────────────────────

    private function wrapInFormatterCatch(Stmt\Expression $stmt, string $outputKey, Expr $path): Stmt\TryCatch
    {
        return Ast::tryCatch(
            [$stmt],
            [
                Ast::catch_(['\Throwable'], 'e', [
                    HydraAst::appendDehydrationError(
                        'INVALID_VALUE',
                        Ast::methodCall(Ast::var('e'), 'getMessage'),
                        $outputKey,
                        $path
                    ),
                ]),
            ]
        );
    }

    /** @return list<Stmt> */
    private function generateUndefinableDehydration(
        PropertyMetadata $prop,
        string $name,
        string $outputKey,
        GenerationContext $ctx,
        Expr $path,
    ): array {
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($ctx->shortName, 'class'),
                Ast::string($name),
            ])
        );
        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);

        $dehydrated = $this->callDehydrateFormatter($name, HydraAst::objectProperty($name));
        if ($prop->isNullable) {
            $thenStmts = [$this->wrapInFormatterCatch(
                HydraAst::assignResult(
                    $outputKey,
                    Ast::ternary(
                        Ast::notIdentical(HydraAst::objectProperty($name), Ast::null()),
                        $dehydrated,
                        Ast::null()
                    )
                ),
                $outputKey,
                $path
            )];
        } else {
            $thenStmts = [$this->wrapInFormatterCatch(HydraAst::assignResult($outputKey, $dehydrated), $outputKey, $path)];
        }

        $elseStmts = null;
        if ($prop->shouldEmitUndefinedDehydrationValue()) {
            $elseStmts = [HydraAst::assignResult($outputKey, Ast::toExpr($prop->undefinedDehydrationValue))];
        }

        return [Ast::expr($refProp), Ast::if($isInitialized, $thenStmts, $elseStmts)];
    }

    // ─────────────────────────────────────────────────────────────
    // Formatter call expressions
    // ─────────────────────────────────────────────────────────────

    /**
     * ($this->formatters[$name]['hydrate'] !== null)
     *     ? ($this->formatters[$name]['hydrate'])($rawValue)
     *     : $rawValue.
     */
    private function callHydrateFormatter(string $name, Expr $rawValue): Expr
    {
        $closure = $this->formatterSlot($name, 'hydrate');

        return Ast::ternary(
            Ast::notIdentical($closure, Ast::null()),
            new Expr\FuncCall($closure, [new Arg($rawValue)]),
            $rawValue
        );
    }

    /**
     * ($this->formatters[$name]['dehydrate'] !== null)
     *     ? ($this->formatters[$name]['dehydrate'])($rawValue)
     *     : $rawValue.
     */
    private function callDehydrateFormatter(string $name, Expr $rawValue): Expr
    {
        $closure = $this->formatterSlot($name, 'dehydrate');

        return Ast::ternary(
            Ast::notIdentical($closure, Ast::null()),
            new Expr\FuncCall($closure, [new Arg($rawValue)]),
            $rawValue
        );
    }

    /** $this->formatters[$name][$slot] */
    private function formatterSlot(string $name, string $slot): Expr\ArrayDimFetch
    {
        return Ast::arrayAccess(
            Ast::arrayAccess(
                Ast::property(Ast::var('this'), 'formatters'),
                Ast::string($name)
            ),
            Ast::string($slot)
        );
    }
}
