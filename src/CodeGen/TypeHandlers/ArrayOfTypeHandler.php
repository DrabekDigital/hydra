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
    /** @var list<string> */
    private const SUPPORTED_SCALAR_TYPES = ['string', 'int', 'float', 'bool', 'null', 'mixed'];

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

        $typeSpec = $this->resolveItemTypeSpec($attr->type, $name);
        $itemType = $typeSpec['displayType'];
        $isObjectType = $typeSpec['isObjectType'];
        $objectType = $typeSpec['objectType'];
        $scalarTypes = $typeSpec['scalarTypes'];

        if ($isObjectType) {
            if ($objectType === null) {
                throw new \RuntimeException("ArrayOf object type resolution failed for '{$name}'.");
            }
            $context->registerNestedType($objectType);
        }

        if ($property->isUndefinable) {
            return [Ast::if(HydraAst::keyExists($key), $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes))];
        }

        if ($property->isRequired()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing required key '{$key}'"), $name, $path)],
                $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes),
            )];
        }

        if ($property->isNullable && !$property->hasAnyDefault()) {
            return [Ast::if(
                HydraAst::keyMissing($key),
                [HydraAst::appendHydrationError('MISSING_KEY', Ast::string("Missing key '{$key}' (nullable but key must exist)"), $name, $path)],
                $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes),
            )];
        }

        return [Ast::if(
            HydraAst::keyExists($key),
            $this->generateThenStmts($property, $key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes),
            [HydraAst::assignArg($name, Ast::toExpr($property->getEffectiveDefault()))],
        )];
    }

    /**
     * @return Stmt[]
     */
    public function generateDehydration(PropertyMetadata $property, GenerationContext $context): array
    {
        $name = $property->name;
        $outputKey = $context->outputKey($name);
        $attr = $property->getAttribute(ArrayOf::class);
        if (!$attr instanceof ArrayOf) {
            throw new \InvalidArgumentException("Missing ArrayOf attribute for '{$name}'");
        }

        $typeSpec = $this->resolveItemTypeSpec($attr->type, $name);
        $itemType = $typeSpec['displayType'];
        $isObjectType = $typeSpec['isObjectType'];
        $objectType = $typeSpec['objectType'];
        $scalarTypes = $typeSpec['scalarTypes'];
        $path = HydraAst::pathExpr($name);

        if ($property->isUndefinable) {
            return $this->generateUndefinableDehydration($property, $name, $outputKey, $itemType, $isObjectType, $objectType, $scalarTypes, $path, $context);
        }

        if ($property->isNullable) {
            return [
                Ast::if(
                    Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                    [HydraAst::assignResult($outputKey, Ast::null())],
                    $this->generateDehydrationValidationStmts($name, $outputKey, $itemType, $isObjectType, $objectType, $scalarTypes, $path),
                ),
            ];
        }

        return $this->generateDehydrationValidationStmts($name, $outputKey, $itemType, $isObjectType, $objectType, $scalarTypes, $path);
    }

    /**
     * @param list<string> $scalarTypes
     *
     * @return list<Stmt>
     */
    private function generateThenStmts(
        PropertyMetadata $property,
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
        ?string $objectType,
        array $scalarTypes,
    ): array {
        if ($property->isNullable) {
            return [
                Ast::if(
                    Ast::identical(HydraAst::dataAccess($key), Ast::null()),
                    [HydraAst::assignArg($name, Ast::null())],
                    $this->generateArrayValidationStmts($key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes),
                ),
            ];
        }

        return $this->generateArrayValidationStmts($key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes);
    }

    /**
     * @param list<string> $scalarTypes
     *
     * @return list<Stmt>
     */
    private function generateArrayValidationStmts(
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
        ?string $objectType,
        array $scalarTypes,
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
                $this->generateProcessStmts($key, $name, $path, $itemType, $isObjectType, $objectType, $scalarTypes),
            ),
        ];
    }

    /**
     * @param list<string> $scalarTypes
     *
     * @return list<Stmt>
     */
    private function generateProcessStmts(
        string $key,
        string $name,
        Expr $path,
        string $itemType,
        bool $isObjectType,
        ?string $objectType,
        array $scalarTypes,
    ): array {
        $itemPathVar = "itemPath_{$name}";
        $arrayKeyVar = "arrayKey_{$name}";

        if ($isObjectType) {
            if ($objectType === null) {
                throw new \RuntimeException("ArrayOf object type resolution failed for '{$name}'.");
            }
            $itemTypeCheck = Ast::funcCall('is_a', [Ast::var('item'), Ast::classConst($objectType, 'class')]);
        } else {
            $itemTypeCheck = $this->buildScalarTypeCheck(Ast::var('item'), $scalarTypes, acceptIntAsFloat: true);
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
                                $isObjectType ? Ast::var('item') : HydraAst::widenIntToFloat(Ast::var('item'), $scalarTypes)
                            )),
                        ],
                    ),
                ],
                Ast::var($arrayKeyVar),
            ),
            HydraAst::assignArg($name, Ast::var("result_{$name}")),
        ];
    }

    /**
     * @param list<string> $scalarTypes
     *
     * @return list<Stmt>
     */
    private function generateDehydrationValidationStmts(
        string $name,
        string $outputKey,
        string $itemType,
        bool $isObjectType,
        ?string $objectType,
        array $scalarTypes,
        Expr $pathExpr,
    ): array {
        $itemPathVar = "dehydrateItemPath_{$name}";
        $arrayKeyVar = "dehydrateArrayKey_{$name}";
        $resultVar = "dehydrateResult_{$name}";

        if ($isObjectType) {
            if ($objectType === null) {
                throw new \RuntimeException("ArrayOf object type resolution failed for '{$name}'.");
            }
            $itemTypeCheck = Ast::funcCall('is_a', [Ast::var('item'), Ast::classConst($objectType, 'class')]);
        } else {
            $itemTypeCheck = $this->buildScalarTypeCheck(Ast::var('item'), $scalarTypes);
        }

        if ($itemTypeCheck === null) {
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
                    Ast::expr(Ast::assign(Ast::var($resultVar), Ast::array([]))),
                    Ast::foreach(
                        HydraAst::objectProperty($name),
                        Ast::var('item'),
                        [
                            Ast::expr(Ast::assign(
                                Ast::var($itemPathVar),
                                Ast::funcCall('sprintf', [Ast::string('%s[%s]'), $pathExpr, Ast::var($arrayKeyVar)])
                            )),
                            Ast::if(
                                Ast::not($itemTypeCheck),
                                [HydraAst::appendDehydrationError(
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
                                        Ast::arrayAccess(Ast::var($resultVar), Ast::var($arrayKeyVar)),
                                        Ast::var('item')
                                    )),
                                ]
                            ),
                        ],
                        Ast::var($arrayKeyVar),
                    ),
                    HydraAst::assignResult($outputKey, Ast::var($resultVar)),
                ]
            ),
        ];
    }

    /**
     * @param list<string> $scalarTypes
     *
     * @return list<Stmt>
     */
    private function generateUndefinableDehydration(
        PropertyMetadata $property,
        string $name,
        string $outputKey,
        string $itemType,
        bool $isObjectType,
        ?string $objectType,
        array $scalarTypes,
        Expr $pathExpr,
        GenerationContext $context,
    ): array {
        $refProp = Ast::assign(
            Ast::var("ref_{$name}"),
            Ast::new('\ReflectionProperty', [
                Ast::classConst($context->shortName, 'class'),
                Ast::string($name),
            ])
        );

        $isInitialized = Ast::methodCall(Ast::var("ref_{$name}"), 'isInitialized', [Ast::var('object')]);
        $thenStmts = $property->isNullable
            ? [
                Ast::if(
                    Ast::identical(HydraAst::objectProperty($name), Ast::null()),
                    [HydraAst::assignResult($outputKey, Ast::null())],
                    $this->generateDehydrationValidationStmts($name, $outputKey, $itemType, $isObjectType, $objectType, $scalarTypes, $pathExpr),
                ),
            ]
            : $this->generateDehydrationValidationStmts($name, $outputKey, $itemType, $isObjectType, $objectType, $scalarTypes, $pathExpr);

        $elseStmts = null;
        if ($property->shouldEmitUndefinedDehydrationValue()) {
            $elseStmts = [HydraAst::assignResult($outputKey, Ast::toExpr($property->undefinedDehydrationValue))];
        }

        return [
            Ast::expr($refProp),
            Ast::if($isInitialized, $thenStmts, $elseStmts),
        ];
    }

    /**
     * @return array{
     *   displayType: string,
     *   isObjectType: bool,
     *   objectType: null|string,
     *   scalarTypes: list<string>
     * }
     */
    private function resolveItemTypeSpec(string $rawType, string $propertyName): array
    {
        if (str_contains($rawType, '|')) {
            return $this->parseScalarUnionType($rawType, $propertyName);
        }

        if (in_array($rawType, self::SUPPORTED_SCALAR_TYPES, true)) {
            return [
                'displayType' => $rawType,
                'isObjectType' => false,
                'objectType' => null,
                'scalarTypes' => [$rawType],
            ];
        }

        if ($rawType === 'array') {
            throw new \InvalidArgumentException("Invalid ArrayOf type 'array' for '{$propertyName}'. Use scalar type(s) or object class.");
        }

        if ($this->analyzer->isObjectType($rawType)) {
            return [
                'displayType' => $rawType,
                'isObjectType' => true,
                'objectType' => $rawType,
                'scalarTypes' => [],
            ];
        }

        throw new \InvalidArgumentException("Invalid ArrayOf type '{$rawType}' for '{$propertyName}'.");
    }

    /**
     * @return array{
     *   displayType: string,
     *   isObjectType: bool,
     *   objectType: null|string,
     *   scalarTypes: list<string>
     * }
     */
    private function parseScalarUnionType(string $rawType, string $propertyName): array
    {
        if (preg_match('/\s/', $rawType) === 1) {
            throw new \InvalidArgumentException("Invalid ArrayOf union type '{$rawType}' for '{$propertyName}': spaces are not allowed.");
        }

        $types = explode('|', $rawType);
        if (in_array('', $types, true)) {
            throw new \InvalidArgumentException("Invalid ArrayOf union type '{$rawType}' for '{$propertyName}'.");
        }

        foreach ($types as $type) {
            if ($type === 'array') {
                throw new \InvalidArgumentException("Invalid ArrayOf union type '{$rawType}' for '{$propertyName}': 'array' is not supported.");
            }
            if (!in_array($type, self::SUPPORTED_SCALAR_TYPES, true)) {
                throw new \InvalidArgumentException("Invalid ArrayOf union type '{$rawType}' for '{$propertyName}'.");
            }
        }

        /** @var list<string> $uniqueTypes */
        $uniqueTypes = array_values(array_unique($types));

        return [
            'displayType' => implode('|', $uniqueTypes),
            'isObjectType' => false,
            'objectType' => null,
            'scalarTypes' => $uniqueTypes,
        ];
    }

    /**
     * @param list<string> $scalarTypes
     */
    private function buildScalarTypeCheck(Expr $value, array $scalarTypes, bool $acceptIntAsFloat = false): ?Expr
    {
        if (in_array('mixed', $scalarTypes, true)) {
            return null;
        }

        $checks = [];
        foreach ($scalarTypes as $scalarType) {
            $check = $scalarType === 'null'
                ? Ast::identical($value, Ast::null())
                : HydraAst::scalarTypeCheck($value, $scalarType, $acceptIntAsFloat && !in_array('int', $scalarTypes, true));
            if ($check === null) {
                throw new \InvalidArgumentException("Invalid scalar type '{$scalarType}' in ArrayOf.");
            }
            $checks[] = $check;
        }

        if ($checks === []) {
            throw new \InvalidArgumentException('ArrayOf scalar type union cannot be empty.');
        }

        return Ast::anyOf(...$checks);
    }
}
