<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit;

use DrabekDigital\Hydra\Attributes\DateTime;
use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AllScalarsDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ArticleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AttributeDefaultDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DateTimeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DefaultValueDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\EnumDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NamingStrategyClaimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NoConstructorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NullableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PersonDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ProductDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashDateTimeRangeMin1900DTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashDateTimeRangeMin2000DTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashFormatterLowerTrimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashFormatterTrimDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashNoDefaultDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashNoNamingStrategyDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashOrderFirstDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashOrderSecondDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashPlainDateDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\StructureHashWithDefaultDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\TagDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableAsNullDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UndefinableWithFallbackDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\UnionScalarDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\MoneyMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ClassAnalyzerTest extends TestCase
{
    private ClassAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ClassAnalyzer();
    }

    // =========================================================================
    // Basic Analysis
    // =========================================================================

    public function testAnalyzesSimpleDto(): void
    {
        $properties = $this->analyzer->analyze(SimpleDTO::class);

        $this->assertCount(2, $properties);
        $this->assertSame('name', $properties[0]->name);
        $this->assertSame(['string'], $properties[0]->types);
        $this->assertTrue($properties[0]->isBuiltin);

        $this->assertSame('age', $properties[1]->name);
        $this->assertSame(['int'], $properties[1]->types);
    }

    public function testAnalyzesAllScalarTypes(): void
    {
        $properties = $this->analyzer->analyze(AllScalarsDTO::class);

        $this->assertCount(4, $properties);

        $typeMap = [];
        foreach ($properties as $prop) {
            $typeMap[$prop->name] = $prop->types;
        }

        $this->assertSame(['string'], $typeMap['string']);
        $this->assertSame(['int'], $typeMap['int']);
        $this->assertSame(['float'], $typeMap['float']);
        $this->assertSame(['bool'], $typeMap['bool']);
    }

    public function testDetectsNullableProperties(): void
    {
        $properties = $this->analyzer->analyze(NullableDTO::class);

        $this->assertTrue($properties[0]->isNullable);
        $this->assertTrue($properties[1]->isNullable);
    }

    public function testDetectsUnionTypes(): void
    {
        $properties = $this->analyzer->analyze(UnionScalarDTO::class);

        $numberProp = $properties[0];
        $this->assertContains('int', $numberProp->types);
        $this->assertContains('float', $numberProp->types);
        $this->assertTrue($numberProp->isBuiltin);
    }

    // =========================================================================
    // Default Values
    // =========================================================================

    public function testDetectsParameterDefaults(): void
    {
        $properties = $this->analyzer->analyze(DefaultValueDTO::class);

        $nameProp = $properties[0];
        $this->assertFalse($nameProp->hasDefault);

        $statusProp = $properties[1];
        $this->assertTrue($statusProp->hasDefault);
        $this->assertSame('active', $statusProp->defaultValue);
    }

    public function testDetectsAttributeDefaults(): void
    {
        $properties = $this->analyzer->analyze(AttributeDefaultDTO::class);

        $statusProp = $properties[1];
        $this->assertTrue($statusProp->hasAttributeDefault);
        $this->assertSame('pending', $statusProp->attributeDefaultValue);
    }

    public function testDetectsUndefinableAttribute(): void
    {
        $properties = $this->analyzer->analyze(UndefinableDTO::class);

        $optionalProp = $properties[1];
        $this->assertTrue($optionalProp->isUndefinable);
        $this->assertNull($optionalProp->undefinedDehydrationValue);
        $this->assertFalse($optionalProp->hasUndefinedDehydrationFallback);
    }

    public function testDetectsUndefinableOnDehydrationWhen(): void
    {
        $properties = $this->analyzer->analyze(UndefinableWithFallbackDTO::class);

        $optionalProp = $properties[1];
        $this->assertTrue($optionalProp->isUndefinable);
        $this->assertSame('missing', $optionalProp->undefinedDehydrationValue);
        $this->assertTrue($optionalProp->hasUndefinedDehydrationFallback);
    }

    public function testDetectsUndefinableAsNullPolicy(): void
    {
        $properties = $this->analyzer->analyze(UndefinableAsNullDTO::class);

        $optionalProp = $properties[1];
        $this->assertTrue($optionalProp->isUndefinable);
        $this->assertNull($optionalProp->undefinedDehydrationValue);
        $this->assertTrue($optionalProp->hasUndefinedDehydrationFallback);
    }

    // =========================================================================
    // Public properties (no constructor)
    // =========================================================================

    public function testAnalyzesPublicPropertiesWhenNoConstructor(): void
    {
        $properties = $this->analyzer->analyze(NoConstructorDTO::class);

        $this->assertCount(4, $properties);

        $byName = [];
        foreach ($properties as $property) {
            $byName[$property->name] = $property;
        }

        $this->assertSame(['string'], $byName['title']->types);
        $this->assertSame(['int'], $byName['count']->types);
        $this->assertTrue($byName['description']->isNullable);
        $this->assertTrue($byName['description']->hasDefault);
        $this->assertNull($byName['description']->defaultValue);
        $this->assertTrue($byName['active']->hasDefault);
        $this->assertTrue($byName['active']->defaultValue);
    }

    // =========================================================================
    // Nested Objects
    // =========================================================================

    public function testDetectsNestedObjectType(): void
    {
        $properties = $this->analyzer->analyze(PersonDTO::class);

        $addressProp = $properties[1];
        $this->assertSame(['DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO'], $addressProp->types);
        $this->assertFalse($addressProp->isBuiltin);
    }

    public function testIsObjectTypeReturnsTrueForClasses(): void
    {
        $this->assertTrue($this->analyzer->isObjectType(SimpleDTO::class));
        $this->assertTrue($this->analyzer->isObjectType(\DateTimeImmutable::class));
    }

    public function testIsObjectTypeReturnsFalseForScalars(): void
    {
        $this->assertFalse($this->analyzer->isObjectType('string'));
        $this->assertFalse($this->analyzer->isObjectType('int'));
        $this->assertFalse($this->analyzer->isObjectType('float'));
        $this->assertFalse($this->analyzer->isObjectType('bool'));
        $this->assertFalse($this->analyzer->isObjectType('array'));
        $this->assertFalse($this->analyzer->isObjectType('mixed'));
    }

    public function testIsObjectTypeReturnsFalseForExcludedPseudoTypes(): void
    {
        $this->assertFalse($this->analyzer->isObjectType('null'));
        $this->assertFalse($this->analyzer->isObjectType('callable'));
        $this->assertFalse($this->analyzer->isObjectType('iterable'));
        $this->assertFalse($this->analyzer->isObjectType('object'));
    }

    public function testIsObjectTypeReturnsFalseForUnknownClass(): void
    {
        $this->assertFalse($this->analyzer->isObjectType('DrabekDigital\Hydra\Tests\Fixtures\Dto\DoesNotExistDTO'));
    }

    // =========================================================================
    // Attributes
    // =========================================================================

    public function testExtractsListOfAttribute(): void
    {
        $properties = $this->analyzer->analyze(ArticleDTO::class);

        $tagsProp = $properties[1];
        $listOfAttr = $tagsProp->getAttribute(ListOf::class);

        $this->assertNotNull($listOfAttr);
        $this->assertSame(TagDTO::class, $listOfAttr->value);
    }

    public function testExtractsDatetimeAttribute(): void
    {
        $properties = $this->analyzer->analyze(DateTimeDTO::class);

        $dateProp = $properties[1];
        $dateTimeAttr = $dateProp->getAttribute(DateTime::class);

        $this->assertNotNull($dateTimeAttr);
        $this->assertSame('Y-m-d', $dateTimeAttr->format);
    }

    public function testExtractsMapperAttribute(): void
    {
        $properties = $this->analyzer->analyze(ProductDTO::class);

        $priceProp = $properties[1];
        $mapperAttr = null;

        foreach ($priceProp->attributes as $attr) {
            if ($attr instanceof MapperAttribute) {
                $mapperAttr = $attr;

                break;
            }
        }

        $this->assertNotNull($mapperAttr);
        $this->assertSame(MoneyMapper::class, $mapperAttr->getMapperClass());
    }

    // =========================================================================
    // Enums
    // =========================================================================

    public function testDetectsBackedEnumTypes(): void
    {
        $properties = $this->analyzer->analyze(EnumDTO::class);

        $statusProp = $properties[0];
        $this->assertFalse($statusProp->isBuiltin);
        $this->assertContains(Status::class, $statusProp->types);
    }

    // =========================================================================
    // Structure Hash
    // =========================================================================

    public function testStructureHashIsStableForSameClass(): void
    {
        $hash1 = $this->analyzer->getStructureHash(SimpleDTO::class);
        $hash2 = $this->analyzer->getStructureHash(SimpleDTO::class);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(12, strlen($hash1));
    }

    public function testStructureHashDifferentForDifferentClasses(): void
    {
        $hash1 = $this->analyzer->getStructureHash(SimpleDTO::class);
        $hash2 = $this->analyzer->getStructureHash(NullableDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStructureHashDiffersWhenParameterOrderDiffers(): void
    {
        $hash1 = $this->analyzer->getStructureHash(StructureHashOrderFirstDTO::class);
        $hash2 = $this->analyzer->getStructureHash(StructureHashOrderSecondDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStructureHashDiffersWhenParameterDefaultsChange(): void
    {
        $hash1 = $this->analyzer->getStructureHash(StructureHashWithDefaultDTO::class);
        $hash2 = $this->analyzer->getStructureHash(StructureHashNoDefaultDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStructureHashDiffersWhenParameterAttributesChange(): void
    {
        $hash1 = $this->analyzer->getStructureHash(DateTimeDTO::class);
        $hash2 = $this->analyzer->getStructureHash(StructureHashPlainDateDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStructureHashDiffersWhenUndefinablePolicyChanges(): void
    {
        $hashSkip = $this->analyzer->getStructureHash(UndefinableDTO::class);
        $hashAsNull = $this->analyzer->getStructureHash(UndefinableAsNullDTO::class);

        $this->assertNotSame($hashSkip, $hashAsNull);
    }

    public function testStructureHashDiffersWhenDateTimeAttributeValueChanges(): void
    {
        $hash1900 = $this->analyzer->getStructureHash(StructureHashDateTimeRangeMin1900DTO::class);
        $hash2000 = $this->analyzer->getStructureHash(StructureHashDateTimeRangeMin2000DTO::class);

        $this->assertNotSame($hash1900, $hash2000);
    }

    public function testStructureHashDiffersWhenClosureFormatterBodyChanges(): void
    {
        $trimHash = $this->analyzer->getStructureHash(StructureHashFormatterTrimDTO::class);
        $lowerTrimHash = $this->analyzer->getStructureHash(StructureHashFormatterLowerTrimDTO::class);

        $this->assertNotSame($trimHash, $lowerTrimHash);
    }

    public function testStructureHashDiffersWhenClassLevelAttributesChange(): void
    {
        $hash1 = $this->analyzer->getStructureHash(NamingStrategyClaimDTO::class);
        $hash2 = $this->analyzer->getStructureHash(StructureHashNoNamingStrategyDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    public function testStructureHashUsesPublicPropertiesPathWhenNoConstructor(): void
    {
        $hash1 = $this->analyzer->getStructureHash(NoConstructorDTO::class);
        $hash2 = $this->analyzer->getStructureHash(NoConstructorDTO::class);

        $this->assertSame($hash1, $hash2);
        $this->assertNotSame(
            $hash1,
            $this->analyzer->getStructureHash(SimpleDTO::class)
        );
    }

    public function testResolvePropertySourceDistinguishesConstructorAndPublicProperties(): void
    {
        $constructorClass = new class('John', 'Doe') {
            public function __construct(
                public string $first,
                public string $last,
            ) {}
        };

        $propertyOnlyClass = new class {
            public string $first;
            public string $last;
        };

        $method = new \ReflectionMethod(ClassAnalyzer::class, 'resolvePropertySource');

        $constructorSource = $method->invoke($this->analyzer, new \ReflectionClass($constructorClass));
        $propertySource = $method->invoke($this->analyzer, new \ReflectionClass($propertyOnlyClass));

        $this->assertSame('constructor_parameters', $constructorSource);
        $this->assertSame('public_properties', $propertySource);
    }

    public function testAnalyzePublicPropertiesCoversStaticUnionAndAttributesBranches(): void
    {
        $object = new class {
            public static string $ignored = 'ignored';

            #[DefaultValue('fallback')]
            #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_VALUE, undefinedAs: 'missing')]
            public int|\stdClass|null $unionWithAttributes;
        };

        $properties = $this->analyzer->analyze($object::class);
        $this->assertCount(1, $properties);

        $property = $properties[0];
        $this->assertSame('unionWithAttributes', $property->name);
        $this->assertContains(\stdClass::class, $property->types);
        $this->assertContains('int', $property->types);
        $this->assertTrue($property->isNullable);
        $this->assertFalse($property->isBuiltin);
        $this->assertTrue($property->hasAttributeDefault);
        $this->assertSame('fallback', $property->attributeDefaultValue);
        $this->assertTrue($property->isUndefinable);
        $this->assertSame('missing', $property->undefinedDehydrationValue);
    }

    public function testStructureHashSkipsStaticPropertiesOnPublicPropertiesPath(): void
    {
        $object = new class {
            public static string $ignored = 'ignored';
            public string $name = 'John';
        };

        $hash1 = $this->analyzer->getStructureHash($object::class);
        $hash2 = $this->analyzer->getStructureHash($object::class);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(12, strlen($hash1));
    }

    public function testAnalyzeParameterHandlesUnionWithNonBuiltinAndNull(): void
    {
        $object = new class(new \stdClass()) {
            public function __construct(
                public int|\stdClass|null $value,
            ) {}
        };

        $properties = $this->analyzer->analyze($object::class);
        $this->assertCount(1, $properties);

        $property = $properties[0];
        $this->assertSame('value', $property->name);
        $this->assertContains(\stdClass::class, $property->types);
        $this->assertContains('int', $property->types);
        $this->assertTrue($property->isNullable);
        $this->assertFalse($property->isBuiltin);
    }

    public function testGetTypeSignatureReturnsMixedForNullType(): void
    {
        $method = new \ReflectionMethod(ClassAnalyzer::class, 'getTypeSignature');
        $signature = $method->invoke($this->analyzer, null);

        $this->assertSame('mixed', $signature);
    }

    public function testGetTypeSignatureReturnsMixedForIntersectionType(): void
    {
        $object = new class(new class implements \Stringable, \Countable {
            public function __toString(): string
            {
                return 'x';
            }

            public function count(): int
            {
                return 1;
            }
        }) {
            public function __construct(
                public \Countable&\Stringable $value,
            ) {}
        };

        $parameter = new \ReflectionClass($object)->getConstructor()?->getParameters()[0] ?? null;
        $this->assertNotNull($parameter);

        $method = new \ReflectionMethod(ClassAnalyzer::class, 'getTypeSignature');
        $signature = $method->invoke($this->analyzer, $parameter->getType());

        $this->assertSame('mixed', $signature);
    }

    public function testSerializeValueHandlesObjectsAndBackedEnums(): void
    {
        $method = new \ReflectionMethod(ClassAnalyzer::class, 'serializeValue');

        $enumResult = $method->invoke($this->analyzer, Status::Published);
        $objectResult = $method->invoke($this->analyzer, new \stdClass());

        $this->assertSame(json_encode(Status::class.'::'.Status::Published->value), $enumResult);
        $this->assertSame(json_encode(\stdClass::class), $objectResult);
    }
}
