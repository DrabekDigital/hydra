<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\Ast;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Hydra-specific AST building helpers.
 */
final class HydraAst
{
    // ─────────────────────────────────────────────────────────────
    // Type Checking
    // ─────────────────────────────────────────────────────────────

    private const TYPE_CHECK_FUNCTIONS = [
        'string' => 'is_string',
        'int' => 'is_int',
        'float' => 'is_float',
        'bool' => 'is_bool',
        'array' => 'is_array',
    ];
    // ─────────────────────────────────────────────────────────────
    // Data Access
    // ─────────────────────────────────────────────────────────────

    /**
     * $data['key'].
     */
    public static function dataAccess(string $key): Expr\ArrayDimFetch
    {
        return Ast::arrayAccess(Ast::var('data'), $key);
    }

    /**
     * array_key_exists('key', $data).
     */
    public static function keyExists(string $key): Expr\FuncCall
    {
        return Ast::funcCall('array_key_exists', [Ast::string($key), Ast::var('data')]);
    }

    /**
     * !array_key_exists('key', $data).
     */
    public static function keyMissing(string $key): Expr\BooleanNot
    {
        return Ast::not(self::keyExists($key));
    }

    /**
     * is_array($data['key']).
     */
    public static function isArray(string $key): Expr\FuncCall
    {
        return Ast::funcCall('is_array', [self::dataAccess($key)]);
    }

    /**
     * Build type check expression: is_string($data['key']) || is_int($data['key']).
     *
     * @param array<string> $types
     */
    public static function typeCheck(string $key, array $types, bool $nullable = false): ?Expr
    {
        if (in_array('mixed', $types, true)) {
            return null;
        }

        $checks = [];
        $access = self::dataAccess($key);

        if ($nullable) {
            $checks[] = Ast::identical($access, Ast::null());
        }

        foreach ($types as $type) {
            if (isset(self::TYPE_CHECK_FUNCTIONS[$type])) {
                $checks[] = Ast::funcCall(self::TYPE_CHECK_FUNCTIONS[$type], [$access]);
            }
        }

        if (self::widensIntToFloat($types)) {
            $checks[] = Ast::funcCall('is_int', [$access]);
        }

        if (empty($checks)) {
            return null;
        }

        return Ast::anyOf(...$checks);
    }

    /**
     * Get human-readable type string for error messages.
     *
     * @param array<string> $types
     */
    public static function expectedTypeString(array $types, bool $nullable = false): string
    {
        $result = implode('|', $types);
        if ($nullable) {
            $result .= '|null';
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // Path Building
    // ─────────────────────────────────────────────────────────────

    /**
     * $path ? "$path.name" : 'name'.
     */
    public static function pathExpr(string $name): Expr\Ternary
    {
        return Ast::ternary(
            Ast::var('path'),
            Ast::interpolate('path', ".{$name}"),
            Ast::string($name)
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Error Handling
    // ─────────────────────────────────────────────────────────────

    /**
     * new HydrationError(code: ..., programmerMessage: ..., key: ..., fullPath: ...).
     */
    public static function hydrationError(string $code, Expr $message, Expr|string $key, Expr $path): Expr\New_
    {
        return Ast::new('HydrationError', [
            Ast::namedArg('code', Ast::classConst('HydrationErrorCode', $code)),
            Ast::namedArg('programmerMessage', $message),
            Ast::namedArg('key', is_string($key) ? Ast::string($key) : $key),
            Ast::namedArg('fullPath', $path),
        ]);
    }

    /**
     * new DehydrationError(code: ..., programmerMessage: ..., key: ..., fullPath: ...).
     */
    public static function dehydrationError(string $code, Expr $message, Expr|string $key, Expr $path): Expr\New_
    {
        return Ast::new('DehydrationError', [
            Ast::namedArg('code', Ast::classConst('DehydrationErrorCode', $code)),
            Ast::namedArg('programmerMessage', $message),
            Ast::namedArg('key', is_string($key) ? Ast::string($key) : $key),
            Ast::namedArg('fullPath', $path),
        ]);
    }

    /**
     * $errors[] = new HydrationError(...).
     */
    public static function appendHydrationError(string $code, Expr $message, Expr|string $key, Expr $path): Stmt\Expression
    {
        return Ast::expr(
            Ast::assign(
                Ast::arrayAccess(Ast::var('errors'), null),
                self::hydrationError($code, $message, $key, $path)
            )
        );
    }

    /**
     * $errors[] = new DehydrationError(...).
     */
    public static function appendDehydrationError(string $code, Expr $message, Expr|string $key, Expr $path): Stmt\Expression
    {
        return Ast::expr(
            Ast::assign(
                Ast::arrayAccess(Ast::var('errors'), null),
                self::dehydrationError($code, $message, $key, $path)
            )
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Argument Building
    // ─────────────────────────────────────────────────────────────

    /**
     * $args['name'] = $expr.
     */
    public static function assignArg(string $name, Expr $value): Stmt\Expression
    {
        return Ast::expr(
            Ast::assign(
                Ast::arrayAccess(Ast::var('args'), $name),
                $value
            )
        );
    }

    /**
     * $args['name'].
     */
    public static function argAccess(string $name): Expr\ArrayDimFetch
    {
        return Ast::arrayAccess(Ast::var('args'), $name);
    }

    // ─────────────────────────────────────────────────────────────
    // Result Building (for dehydration)
    // ─────────────────────────────────────────────────────────────

    /**
     * $result['name'] = $expr.
     */
    public static function assignResult(string $name, Expr $value): Stmt\Expression
    {
        return Ast::expr(
            Ast::assign(
                Ast::arrayAccess(Ast::var('result'), $name),
                $value
            )
        );
    }

    /**
     * $object->propertyName.
     */
    public static function objectProperty(string $name): Expr\PropertyFetch
    {
        return Ast::property(Ast::var('object'), $name);
    }

    // ─────────────────────────────────────────────────────────────
    // Nested Hydrators/Dehydrators
    // ─────────────────────────────────────────────────────────────

    /**
     * $this->nestedHydrators['ClassName']->hydrate($data['key'], $pathExpr).
     */
    public static function nestedHydrate(string $className, string $key, Expr $path): Expr\MethodCall
    {
        return Ast::methodCall(
            Ast::arrayAccess(
                Ast::property(Ast::var('this'), 'nestedHydrators'),
                $className
            ),
            'hydrate',
            [self::dataAccess($key), $path]
        );
    }

    /**
     * $this->nestedDehydrators['ClassName']->dehydrate($object->property, $pathExpr).
     */
    public static function nestedDehydrate(string $className, string $property, Expr $path): Expr\MethodCall
    {
        return Ast::methodCall(
            Ast::arrayAccess(
                Ast::property(Ast::var('this'), 'nestedDehydrators'),
                $className
            ),
            'dehydrate',
            [self::objectProperty($property), $path]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Common Patterns
    // ─────────────────────────────────────────────────────────────

    /**
     * Wrap statements in try/catch that merges HydrationFailure errors into $errors.
     *
     * @param Stmt[] $stmts
     */
    public static function wrapInHydrationCatch(array $stmts): Stmt\TryCatch
    {
        return Ast::tryCatch($stmts, [
            Ast::catch_(['HydrationFailure'], 'e', [self::mergeErrors()]),
        ]);
    }

    /**
     * Wrap statements in try/catch that merges DehydrationFailure errors into $errors.
     *
     * @param Stmt[] $stmts
     */
    public static function wrapInDehydrationCatch(array $stmts): Stmt\TryCatch
    {
        return Ast::tryCatch($stmts, [
            Ast::catch_(['DehydrationFailure'], 'e', [self::mergeErrors()]),
        ]);
    }

    /**
     * Wrap full generated hydration runtime to guarantee domain-level failure contract.
     *
     * @param Stmt[] $stmts
     */
    public static function wrapHydrationRuntimeBoundary(array $stmts): Stmt\TryCatch
    {
        return Ast::tryCatch($stmts, [
            Ast::catch_(['HydrationFailure'], 'e', [Ast::throw(Ast::var('e'))]),
            Ast::catch_(['\Throwable'], 'e', [
                Ast::throw(Ast::new('HydrationFailure', [[self::hydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(
                        Ast::string('Runtime hydration failure: '),
                        Ast::methodCall(Ast::var('e'), 'getMessage')
                    ),
                    Ast::null(),
                    Ast::var('path')
                )]])),
            ]),
        ]);
    }

    /**
     * Wrap full generated dehydration runtime to guarantee domain-level failure contract.
     *
     * @param Stmt[] $stmts
     */
    public static function wrapDehydrationRuntimeBoundary(array $stmts): Stmt\TryCatch
    {
        return Ast::tryCatch($stmts, [
            Ast::catch_(['DehydrationFailure'], 'e', [Ast::throw(Ast::var('e'))]),
            Ast::catch_(['\Throwable'], 'e', [
                Ast::throw(Ast::new('DehydrationFailure', [[self::dehydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(
                        Ast::string('Runtime dehydration failure: '),
                        Ast::methodCall(Ast::var('e'), 'getMessage')
                    ),
                    Ast::null(),
                    Ast::var('path')
                )]])),
            ]),
        ]);
    }

    /**
     * if (!empty($errors)) { throw new HydrationFailure($errors); }.
     */
    public static function throwIfErrors(string $exceptionClass = 'HydrationFailure'): Stmt\If_
    {
        return Ast::if(
            Ast::not(Ast::funcCall('empty', [Ast::var('errors')])),
            [Ast::throw(Ast::new($exceptionClass, [Ast::var('errors')]))]
        );
    }

    /**
     * Error message with gettype(): "Expected X for 'name', got " . gettype($data['key']).
     */
    public static function typeMismatchMessage(string $expected, string $name, string $key): Expr
    {
        return Ast::concat(
            Ast::string("Expected {$expected} for '{$name}', got "),
            Ast::funcCall('gettype', [self::dataAccess($key)])
        );
    }

    /**
     * Build scalar type check expression for a dynamic value expression.
     *
     * With $acceptIntAsFloat, 'float' also accepts int (widening, as PHP does under strict_types).
     */
    public static function scalarTypeCheck(Expr $value, string $type, bool $acceptIntAsFloat = false): ?Expr
    {
        return match ($type) {
            'string' => Ast::funcCall('is_string', [$value]),
            'int' => Ast::funcCall('is_int', [$value]),
            'float' => $acceptIntAsFloat
                ? Ast::anyOf(Ast::funcCall('is_float', [$value]), Ast::funcCall('is_int', [$value]))
                : Ast::funcCall('is_float', [$value]),
            'bool' => Ast::funcCall('is_bool', [$value]),
            'array' => Ast::funcCall('is_array', [$value]),
            'mixed' => null,
            default => null,
        };
    }

    /**
     * is_int($value) ? (float) $value : $value — when the declared types accept float but not int.
     *
     * JSON numbers without a fraction (e.g. 5) decode to int, so a float property must accept them.
     * Casting keeps the hydrated value a real float (needed for list/map items, which PHP does not coerce).
     *
     * @param array<string> $types
     */
    public static function widenIntToFloat(Expr $value, array $types): Expr
    {
        if (!self::widensIntToFloat($types)) {
            return $value;
        }

        return Ast::ternary(
            Ast::funcCall('is_int', [$value]),
            new Expr\Cast\Double($value, ['kind' => Expr\Cast\Double::KIND_FLOAT]),
            $value
        );
    }

    /**
     * @param array<string> $types
     */
    public static function widensIntToFloat(array $types): bool
    {
        return in_array('float', $types, true) && !in_array('int', $types, true);
    }

    /**
     * foreach ($e->getErrors() as $nestedError) { $errors[] = $nestedError; }.
     */
    private static function mergeErrors(): Stmt\Foreach_
    {
        return Ast::foreach(
            Ast::methodCall(Ast::var('e'), 'getErrors'),
            Ast::var('nestedError'),
            [
                Ast::expr(Ast::assign(
                    Ast::arrayAccess(Ast::var('errors'), null),
                    Ast::var('nestedError')
                )),
            ]
        );
    }
}
