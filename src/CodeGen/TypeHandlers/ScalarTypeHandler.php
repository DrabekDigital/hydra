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
 * Handles scalar types: string, int, float, bool, array, mixed.
 */
final class ScalarTypeHandler implements TypeHandler
{
    public function priority(): int
    {
        return 0; // Lowest priority - fallback handler
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->isBuiltin;
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinable($property, $key, $name, $path);
        }

        if ($property->isRequired()) {
            return $this->generateRequired($property, $key, $name, $path);
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return $this->generateNullableRequiredKey($property, $key, $name, $path);
        }

        return $this->generateWithDefault($property, $key, $name, $path);
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $context);
        }

        $assign = HydraAst::assignResult($outputKey, HydraAst::objectProperty($name));

        if ($context->isPropertyOnly) {
            $path = HydraAst::pathExpr($name);
            $context->markDehydrationErrorsGenerated();

            return [Ast::tryCatch(
                [$assign],
                [Ast::catch_(['\Error'], 'e', [
                    HydraAst::appendDehydrationError(
                        'UNEXPECTED_NULL',
                        Ast::string("Property '{$name}' is not initialized"),
                        $outputKey,
                        $path
                    ),
                ])]
            )];
        }

        return [$assign];
    }

    // ─────────────────────────────────────────────────────────────
    // Hydration Generators
    // ─────────────────────────────────────────────────────────────

    /**
     * @return Stmt[]
     */
    private function generateRequired(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        $typeCheck = HydraAst::typeCheck($key, $prop->types);
        $expected = HydraAst::expectedTypeString($prop->types);

        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path),
        ];

        if ($typeCheck !== null) {
            $elseStmts = [
                Ast::if(
                    Ast::not($typeCheck),
                    [HydraAst::appendHydrationError('TYPE_MISMATCH', HydraAst::typeMismatchMessage($expected, $name, $key), $name, $path)],
                    [HydraAst::assignArg($name, HydraAst::dataAccess($key))]
                ),
            ];
        } else {
            $elseStmts = [HydraAst::assignArg($name, HydraAst::dataAccess($key))];
        }

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateNullableRequiredKey(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        $typeCheck = HydraAst::typeCheck($key, $prop->types, nullable: true);
        $expected = HydraAst::expectedTypeString($prop->types, nullable: true);

        $thenStmts = [
            HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path),
        ];

        if ($typeCheck !== null) {
            $elseStmts = [
                Ast::if(
                    Ast::not($typeCheck),
                    [HydraAst::appendHydrationError('TYPE_MISMATCH', HydraAst::typeMismatchMessage($expected, $name, $key), $name, $path)],
                    [HydraAst::assignArg($name, HydraAst::dataAccess($key))]
                ),
            ];
        } else {
            $elseStmts = [HydraAst::assignArg($name, HydraAst::dataAccess($key))];
        }

        return [Ast::if(HydraAst::keyMissing($key), $thenStmts, $elseStmts)];
    }

    /**
     * @return Stmt[]
     */
    private function generateWithDefault(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        $default = Ast::toExpr($prop->getEffectiveDefault());
        $typeCheck = HydraAst::typeCheck($key, $prop->types, $prop->isNullable);
        $expected = HydraAst::expectedTypeString($prop->types, $prop->isNullable);

        if ($typeCheck !== null) {
            $thenStmts = [
                Ast::if(
                    Ast::not($typeCheck),
                    [HydraAst::appendHydrationError('TYPE_MISMATCH', HydraAst::typeMismatchMessage($expected, $name, $key), $name, $path)],
                    [HydraAst::assignArg($name, HydraAst::dataAccess($key))]
                ),
            ];
        } else {
            $thenStmts = [HydraAst::assignArg($name, HydraAst::dataAccess($key))];
        }

        return [Ast::if(HydraAst::keyExists($key), $thenStmts, [HydraAst::assignArg($name, $default)])];
    }

    /**
     * @return Stmt[]
     */
    private function generateUndefinable(PropertyMetadata $prop, string $key, string $name, Expr $path): array
    {
        $typeCheck = HydraAst::typeCheck($key, $prop->types, $prop->isNullable);
        $expected = HydraAst::expectedTypeString($prop->types, $prop->isNullable);

        if ($typeCheck !== null) {
            $thenStmts = [
                Ast::if(
                    Ast::not($typeCheck),
                    [HydraAst::appendHydrationError('TYPE_MISMATCH', HydraAst::typeMismatchMessage($expected, $name, $key), $name, $path)],
                    [HydraAst::assignArg($name, HydraAst::dataAccess($key))]
                ),
            ];
        } else {
            $thenStmts = [HydraAst::assignArg($name, HydraAst::dataAccess($key))];
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
        // Check if property is initialized using reflection
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($ctx->shortName, 'class'),
                Ast::string($name),
            ])
        );

        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);

        $thenStmts = [HydraAst::assignResult($outputKey, HydraAst::objectProperty($name))];

        // If onDehydrationWhen is set, use it for uninitialized properties
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
