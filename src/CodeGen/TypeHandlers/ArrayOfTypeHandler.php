<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen\TypeHandlers;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\CodeGen\Ast\Ast;
use DrabekDigital\Hydra\CodeGen\Ast\HydraAst;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Handles arrays annotated with #[ArrayOf] attribute.
 */
final class ArrayOfTypeHandler implements TypeHandler
{
    public function __construct(
        private readonly ClassAnalyzer $analyzer,
    ) {}

    public function priority(): int
    {
        return 63; // Above ListOf, below MapOf
    }

    public function supports(PropertyMetadata $property, GenerationContext $context): bool
    {
        return $property->hasAttribute(ArrayOf::class);
    }

    /**
     * @return Stmt[]
     */
    public function generateHydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $key = $context->inputKey($name);
        $path = HydraAst::pathExpr($name);
        $attr = $property->getAttribute(ArrayOf::class);
        if (!$attr instanceof ArrayOf) {
            throw new \InvalidArgumentException("Missing ArrayOf attribute for '{$name}'");
        }

        $itemType = $attr->type;
        $isObjectType = $this->analyzer->isObjectType($itemType);
        if ($isObjectType) {
            $context->registerNestedType($itemType);
        }

        if ($property->isUndefinable) {
            return [Ast::if(HydraAst::keyExists($key), $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType))];
        }

        if ($property->isRequired()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
                $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType),
            )];
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
                $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType),
            )];
        }

        return [Ast::if(
            HydraAst::keyExists($key),
            $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType),
            [HydraAst::assignArg($name, Ast::toExpr($property->getEffectiveDefault()))],
        )];
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $outputKey = $context->outputKey($property->name);

        return [HydraAst::assignResult($outputKey, HydraAst::objectProperty($property->name))];
    }

    /**
     * @return list<Stmt>
     */
    private function generateThenStmts(
        PropertyMetadata $property,
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
    ): array {
        if ($property->isNullable) {
            return [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    $this->generateArrayValidationStmts($key, $name, $path, $itemType, $isObjectType),
                ),
            ];
        }

        return $this->generateArrayValidationStmts($key, $name, $path, $itemType, $isObjectType);
    }

    /**
     * @return list<Stmt>
     */
    private function generateArrayValidationStmts(
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
    ): array {
        return [
            Ast::if(
                Ast::not(Ast::funcCall('is_array', [HydraAst::dataAccess($key)])),
                [HydraAst::appendHydrationError(
                    'TYPE_MISMATCH',
                    Ast::concat(Ast::string("Expected array for '{$name}', got "), Ast::funcCall('gettype', [HydraAst::dataAccess($key)])),
                    $name,
                    $path
                )],
                $this->generateProcessStmts($key, $name, $path, $itemType, $isObjectType),
            ),
        ];
    }

    /**
     * @return list<Stmt>
     */
    private function generateProcessStmts(
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
    ): array {
        $itemPathVar = "itemPath_{$name}";
        $arrayKeyVar = "arrayKey_{$name}";

        if ($isObjectType) {
            $itemTypeCheck = Ast::funcCall('is_a', [Ast::var('item'), Ast::classConst($itemType, 'class')]);
        } else {
            $itemTypeCheck = HydraAst::scalarTypeCheck(Ast::var('item'), $itemType);
        }

        if ($itemTypeCheck === null) {
            return [
                HydraAst::assignArg($name, HydraAst::dataAccess($key)),
            ];
        }

        return [
            Ast::expr(Ast::assign(Ast::var("result_{$name}"), Ast::array([]))),
            Ast::expr(Ast::assign(Ast::var("arrayPath_{$name}"), $path)),
            Ast::foreach(
                HydraAst::dataAccess($key),
                Ast::var('item'),
                [
                    Ast::expr(Ast::assign(
                        Ast::var($itemPathVar),
                        Ast::funcCall('sprintf', [Ast::string('%s[%s]'), Ast::var("arrayPath_{$name}"), Ast::var($arrayKeyVar)])
                    )),
                    Ast::if(
                        Ast::not($itemTypeCheck),
                        [HydraAst::appendHydrationError(
                            'TYPE_MISMATCH',
                            Ast::concat(
                                Ast::string("Expected {$itemType} item for '{$name}', got "),
                                Ast::funcCall('gettype', [Ast::var('item')])
                            ),
                            Ast::var($arrayKeyVar),
                            Ast::var($itemPathVar)
                        )],
                        [
                            Ast::expr(Ast::assign(
                                Ast::arrayAccess(Ast::var("result_{$name}"), Ast::var($arrayKeyVar)),
                                Ast::var('item')
                            )),
                        ],
                    ),
                ],
                Ast::var($arrayKeyVar),
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];
    }
}
