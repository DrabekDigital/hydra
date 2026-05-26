<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\Ast;

use PhpParser\BuilderFactory;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

/**
 * Fluent helper for building PHP AST nodes.
 */
final class Ast
{
    private static ?BuilderFactory $factory = null;

    public static function factory(): BuilderFactory
    {
        return self::$factory ??= new BuilderFactory();
    }

    // ─────────────────────────────────────────────────────────────
    // Expressions - Variables & Literals
    // ─────────────────────────────────────────────────────────────

    public static function var(string $name): Expr\Variable
    {
        return new Expr\Variable($name);
    }

    public static function string(string $value): Scalar\String_
    {
        return new Scalar\String_($value);
    }

    public static function int(int $value): Scalar\Int_
    {
        return new Scalar\Int_($value);
    }

    public static function float(float $value): Scalar\Float_
    {
        return new Scalar\Float_($value);
    }

    public static function null(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Node\Name('null'));
    }

    public static function true(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Node\Name('true'));
    }

    public static function false(): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Node\Name('false'));
    }

    public static function bool(bool $value): Expr\ConstFetch
    {
        return $value ? self::true() : self::false();
    }

    public static function const(string $name): Expr\ConstFetch
    {
        return new Expr\ConstFetch(new Node\Name($name));
    }

    public static function classConst(string $class, string $const): Expr\ClassConstFetch
    {
        return new Expr\ClassConstFetch(new Node\Name($class), $const);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    public static function array(array $items = []): Expr\Array_
    {
        $arrayItems = [];
        foreach ($items as $key => $value) {
            $keyExpr = is_int($key) ? null : self::toExpr($key);
            $valueExpr = self::toExpr($value);
            $arrayItems[] = new Node\ArrayItem($valueExpr, $keyExpr);
        }

        return new Expr\Array_($arrayItems, ['kind' => Expr\Array_::KIND_SHORT]);
    }

    // ─────────────────────────────────────────────────────────────
    // Expressions - Access
    // ─────────────────────────────────────────────────────────────

    public static function arrayAccess(Expr $var, Expr|string|null $key = null): Expr\ArrayDimFetch
    {
        $keyExpr = $key === null ? null : (is_string($key) ? self::string($key) : $key);

        return new Expr\ArrayDimFetch($var, $keyExpr);
    }

    public static function property(Expr $var, string $name): Expr\PropertyFetch
    {
        return new Expr\PropertyFetch($var, $name);
    }

    public static function staticProperty(string $class, string $name): Expr\StaticPropertyFetch
    {
        return new Expr\StaticPropertyFetch(new Node\Name($class), $name);
    }

    // ─────────────────────────────────────────────────────────────
    // Expressions - Calls
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<int, mixed> $args
     */
    public static function methodCall(Expr $var, string $method, array $args = []): Expr\MethodCall
    {
        return new Expr\MethodCall($var, $method, self::toArgs($args));
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function staticCall(string $class, string $method, array $args = []): Expr\StaticCall
    {
        return new Expr\StaticCall(new Node\Name($class), $method, self::toArgs($args));
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function funcCall(string $name, array $args = []): Expr\FuncCall
    {
        return new Expr\FuncCall(new Node\Name($name), self::toArgs($args));
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function new(string $class, array $args = []): Expr\New_
    {
        return new Expr\New_(new Node\Name($class), self::toArgs($args));
    }

    // ─────────────────────────────────────────────────────────────
    // Expressions - Operators
    // ─────────────────────────────────────────────────────────────

    public static function assign(Expr $var, Expr $value): Expr\Assign
    {
        return new Expr\Assign($var, $value);
    }

    public static function concat(Expr ...$parts): Expr
    {
        if (count($parts) === 0) {
            return self::string('');
        }
        if (count($parts) === 1) {
            return $parts[0];
        }
        $result = array_shift($parts);
        foreach ($parts as $part) {
            $result = new Expr\BinaryOp\Concat($result, $part);
        }

        return $result;
    }

    public static function ternary(Expr $cond, ?Expr $if, Expr $else): Expr\Ternary
    {
        return new Expr\Ternary($cond, $if, $else);
    }

    public static function coalesce(Expr $left, Expr $right): Expr\BinaryOp\Coalesce
    {
        return new Expr\BinaryOp\Coalesce($left, $right);
    }

    // Comparisons
    public static function identical(Expr $left, Expr $right): Expr\BinaryOp\Identical
    {
        return new Expr\BinaryOp\Identical($left, $right);
    }

    public static function notIdentical(Expr $left, Expr $right): Expr\BinaryOp\NotIdentical
    {
        return new Expr\BinaryOp\NotIdentical($left, $right);
    }

    public static function equals(Expr $left, Expr $right): Expr\BinaryOp\Equal
    {
        return new Expr\BinaryOp\Equal($left, $right);
    }

    public static function notEquals(Expr $left, Expr $right): Expr\BinaryOp\NotEqual
    {
        return new Expr\BinaryOp\NotEqual($left, $right);
    }

    // Boolean
    public static function not(Expr $expr): Expr\BooleanNot
    {
        return new Expr\BooleanNot($expr);
    }

    public static function and(Expr $left, Expr $right): Expr\BinaryOp\BooleanAnd
    {
        return new Expr\BinaryOp\BooleanAnd($left, $right);
    }

    public static function or(Expr $left, Expr $right): Expr\BinaryOp\BooleanOr
    {
        return new Expr\BinaryOp\BooleanOr($left, $right);
    }

    /**
     * Combine multiple expressions with OR.
     */
    public static function anyOf(Expr ...$exprs): Expr
    {
        if (count($exprs) === 0) {
            return self::false();
        }
        if (count($exprs) === 1) {
            return $exprs[0];
        }

        return array_reduce(
            array_slice($exprs, 1),
            fn (Expr $carry, Expr $expr) => self::or($carry, $expr),
            $exprs[0]
        );
    }

    // ─────────────────────────────────────────────────────────────
    // Statements
    // ─────────────────────────────────────────────────────────────

    public static function expr(Expr $expr): Stmt\Expression
    {
        return new Stmt\Expression($expr);
    }

    public static function return(?Expr $expr = null): Stmt\Return_
    {
        return new Stmt\Return_($expr);
    }

    /**
     * @param array<int, Stmt>      $then
     * @param null|array<int, Stmt> $else
     */
    public static function if(Expr $cond, array $then, ?array $else = null): Stmt\If_
    {
        $if = new Stmt\If_($cond, ['stmts' => $then]);
        if ($else !== null) {
            $if->else = new Stmt\Else_($else);
        }

        return $if;
    }

    /**
     * @param array<int, Stmt> $stmts
     */
    public static function elseIf(Expr $cond, array $stmts): Stmt\ElseIf_
    {
        return new Stmt\ElseIf_($cond, $stmts);
    }

    public static function throw(Expr $expr): Stmt\Expression
    {
        return new Stmt\Expression(new Expr\Throw_($expr));
    }

    /**
     * @param Stmt[]        $tryStmts
     * @param Stmt\Catch_[] $catches
     */
    public static function tryCatch(array $tryStmts, array $catches): Stmt\TryCatch
    {
        return new Stmt\TryCatch($tryStmts, $catches);
    }

    /**
     * @param string[] $types e.g. ['DehydrationError', '\Throwable']
     * @param Stmt[]   $stmts
     */
    public static function catch_(array $types, string $varName, array $stmts): Stmt\Catch_
    {
        $typeNames = array_map(fn ($t) => new Node\Name($t), $types);

        return new Stmt\Catch_($typeNames, new Expr\Variable($varName), $stmts);
    }

    /**
     * @param array<int, Stmt> $stmts
     */
    public static function foreach(Expr $array, Expr $value, array $stmts, ?Expr $key = null): Stmt\Foreach_
    {
        return new Stmt\Foreach_($array, $value, [
            'keyVar' => $key,
            'stmts' => $stmts,
        ]);
    }

    public static function use(string $name, ?string $alias = null): Stmt\Use_
    {
        $useUse = new Node\UseItem(new Node\Name($name), $alias);

        return new Stmt\Use_([$useUse]);
    }

    public static function comment(string $text): Stmt\Nop
    {
        $nop = new Stmt\Nop();
        $nop->setDocComment(new Doc("/* {$text} */"));

        return $nop;
    }

    public static function lineComment(string $text): Stmt\Nop
    {
        $nop = new Stmt\Nop();
        $nop->setAttribute('comments', [new Comment("// {$text}")]);

        return $nop;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Convert array to function arguments.
     *
     * @param array<int, mixed> $args Can contain:
     *                                - Expr objects (positional)
     *                                - Node\Arg objects (pass-through)
     *                                - ['name' => Expr] arrays (named arguments)
     *
     * @return array<int, Node\Arg>
     */
    public static function toArgs(array $args): array
    {
        return array_map(function ($arg) {
            if ($arg instanceof Node\Arg) {
                return $arg;
            }
            if (is_array($arg) && count($arg) === 1) {
                $name = array_key_first($arg);
                if (is_string($name)) {
                    return new Node\Arg(self::toExpr($arg[$name]), name: new Node\Identifier($name));
                }
            }

            return new Node\Arg(self::toExpr($arg));
        }, $args);
    }

    public static function namedArg(string $name, mixed $value): Node\Arg
    {
        return new Node\Arg(self::toExpr($value), name: new Node\Identifier($name));
    }

    /**
     * Convert PHP value to AST expression.
     */
    public static function toExpr(mixed $value): Expr
    {
        if ($value instanceof Expr) {
            return $value;
        }

        return match (true) {
            $value === null => self::null(),
            is_bool($value) => self::bool($value),
            is_int($value) => self::int($value),
            is_float($value) => self::float($value),
            is_string($value) => self::string($value),
            is_array($value) => self::array($value),
            default => throw new \InvalidArgumentException('Cannot convert value to expression: '.gettype($value)),
        };
    }

    /**
     * Create interpolated string: "$var.suffix".
     */
    public static function interpolate(string $varName, string $suffix): Scalar\InterpolatedString
    {
        return new Scalar\InterpolatedString([
            new Expr\Variable($varName),
            new Node\InterpolatedStringPart($suffix),
        ]);
    }
}
