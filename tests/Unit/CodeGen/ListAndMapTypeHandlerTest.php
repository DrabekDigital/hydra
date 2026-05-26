<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ListOfTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\MapOfTypeHandler;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ListAndMapTypeHandlerTest extends TestCase
{
    public function testListOfSupportsOnlyWhenAttributePresent(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $context = $this->makeContext();

        $supported = $this->makeProperty(
            name: 'items',
            types: ['array'],
            attributes: [ListOf::class => new ListOf('string')]
        );
        $unsupported = $this->makeProperty(name: 'items', types: ['array']);

        $this->assertTrue($handler->supports($supported, $context));
        $this->assertFalse($handler->supports($unsupported, $context));
    }

    public function testListOfGenerateHydrationThrowsWhenAttributeMissing(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'items', types: ['array']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testListOfGenerateDehydrationThrowsWhenAttributeMissing(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'items', types: ['array']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testListOfGenerateHydrationRequiredScalarBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(name: 'numbers', valueType: 'int');

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testListOfGenerateHydrationRequiredObjectBranchRegistersNestedType(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(name: 'addresses', valueType: AddressDTO::class);
        $context = $this->makeContext([$property]);

        $stmts = $handler->generateHydration($property, $context);

        $this->assertNotEmpty($stmts);
        $this->assertContains(AddressDTO::class, $context->getNestedTypes());
    }

    public function testListOfGenerateHydrationDiscriminatorMappingRegistersAllNestedTypes(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(
            name: 'vehicles',
            valueType: Vehicle::class,
            listOf: new ListOf(
                value: Vehicle::class,
                discriminator: 'type',
                mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class],
                passDiscriminator: false
            )
        );
        $context = $this->makeContext([$property]);

        $stmts = $handler->generateHydration($property, $context);

        $this->assertNotEmpty($stmts);
        $nested = $context->getNestedTypes();
        $this->assertContains(Vehicle::class, $nested);
        $this->assertContains(Car::class, $nested);
        $this->assertContains(Motorcycle::class, $nested);
    }

    public function testListOfGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(name: 'numbers', valueType: 'int', isNullable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testListOfGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(
            name: 'numbers',
            valueType: 'int',
            isNullable: true,
            hasDefault: true,
            defaultValue: []
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testListOfGenerateHydrationUndefinableBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(name: 'numbers', valueType: 'int', isUndefinable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testListOfGenerateHydrationMixedScalarBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(name: 'anything', valueType: 'mixed');

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testListOfGenerateDehydrationSimpleScalarAndObjectBranches(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());

        $scalars = $this->makeListProperty(name: 'numbers', valueType: 'int');
        $objects = $this->makeListProperty(name: 'addresses', valueType: AddressDTO::class);

        $this->assertNotEmpty($handler->generateDehydration($scalars, $this->makeContext([$scalars])));
        $this->assertNotEmpty($handler->generateDehydration($objects, $this->makeContext([$objects])));
    }

    public function testListOfGenerateDehydrationNullableBranchForScalarsAndObjects(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());

        $nullableScalars = $this->makeListProperty(name: 'numbers', valueType: 'int', isNullable: true);
        $nullableObjects = $this->makeListProperty(name: 'addresses', valueType: AddressDTO::class, isNullable: true);

        $this->assertNotEmpty($handler->generateDehydration($nullableScalars, $this->makeContext([$nullableScalars])));
        $this->assertNotEmpty($handler->generateDehydration($nullableObjects, $this->makeContext([$nullableObjects])));
    }

    public function testListOfGenerateDehydrationUndefinableWithAndWithoutFallback(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());

        $withoutFallback = $this->makeListProperty(name: 'items', valueType: 'string', isUndefinable: true);
        $withFallback = $this->makeListProperty(
            name: 'items',
            valueType: 'string',
            isUndefinable: true,
            undefinableOnDehydrationWhen: []
        );

        $this->assertNotEmpty($handler->generateDehydration($withoutFallback, $this->makeContext([$withoutFallback])));
        $this->assertNotEmpty($handler->generateDehydration($withFallback, $this->makeContext([$withFallback])));
    }

    public function testListOfGenerateDehydrationDiscriminatorMappingBranch(): void
    {
        $handler = new ListOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeListProperty(
            name: 'vehicles',
            valueType: Vehicle::class,
            listOf: new ListOf(
                value: Vehicle::class,
                discriminator: 'type',
                mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class],
                passDiscriminator: true
            )
        );

        $this->assertNotEmpty($handler->generateDehydration($property, $this->makeContext([$property])));
    }

    public function testMapOfSupportsOnlyWhenAttributePresent(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $context = $this->makeContext();

        $supported = $this->makeProperty(
            name: 'map',
            types: ['array'],
            attributes: [MapOf::class => new MapOf(value: 'int')]
        );
        $unsupported = $this->makeProperty(name: 'map', types: ['array']);

        $this->assertTrue($handler->supports($supported, $context));
        $this->assertFalse($handler->supports($unsupported, $context));
    }

    public function testMapOfGenerateHydrationThrowsWhenAttributeMissing(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'map', types: ['array']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testMapOfGenerateDehydrationThrowsWhenAttributeMissing(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'map', types: ['array']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testMapOfGenerateHydrationRequiredScalarAndObjectBranches(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());

        $scalars = $this->makeMapProperty(name: 'scores', valueType: 'int');
        $objects = $this->makeMapProperty(name: 'addresses', valueType: AddressDTO::class);
        $context = $this->makeContext([$scalars, $objects]);

        $this->assertNotEmpty($handler->generateHydration($scalars, $context));
        $this->assertNotEmpty($handler->generateHydration($objects, $context));
        $this->assertContains(AddressDTO::class, $context->getNestedTypes());
    }

    public function testMapOfGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeMapProperty(name: 'scores', valueType: 'int', isNullable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testMapOfGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeMapProperty(
            name: 'scores',
            valueType: 'int',
            isNullable: true,
            hasDefault: true,
            defaultValue: []
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testMapOfGenerateHydrationUndefinableBranch(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeMapProperty(name: 'scores', valueType: 'int', isUndefinable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testMapOfGenerateHydrationMixedScalarBranch(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());
        $property = $this->makeMapProperty(name: 'anything', valueType: 'mixed');

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testMapOfGenerateDehydrationSimpleScalarAndObjectBranches(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());

        $scalars = $this->makeMapProperty(name: 'scores', valueType: 'int');
        $objects = $this->makeMapProperty(name: 'addresses', valueType: AddressDTO::class);

        $this->assertNotEmpty($handler->generateDehydration($scalars, $this->makeContext([$scalars])));
        $this->assertNotEmpty($handler->generateDehydration($objects, $this->makeContext([$objects])));
    }

    public function testMapOfGenerateDehydrationNullableScalarAndObjectBranches(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());

        $nullableScalars = $this->makeMapProperty(name: 'scores', valueType: 'int', isNullable: true);
        $nullableObjects = $this->makeMapProperty(name: 'addresses', valueType: AddressDTO::class, isNullable: true);

        $this->assertNotEmpty($handler->generateDehydration($nullableScalars, $this->makeContext([$nullableScalars])));
        $this->assertNotEmpty($handler->generateDehydration($nullableObjects, $this->makeContext([$nullableObjects])));
    }

    public function testMapOfGenerateDehydrationUndefinableWithAndWithoutFallback(): void
    {
        $handler = new MapOfTypeHandler(new ClassAnalyzer());

        $withoutFallback = $this->makeMapProperty(name: 'scores', valueType: 'int', isUndefinable: true);
        $withFallback = $this->makeMapProperty(
            name: 'scores',
            valueType: 'int',
            isUndefinable: true,
            undefinableOnDehydrationWhen: []
        );

        $this->assertNotEmpty($handler->generateDehydration($withoutFallback, $this->makeContext([$withoutFallback])));
        $this->assertNotEmpty($handler->generateDehydration($withFallback, $this->makeContext([$withFallback])));
    }

    /**
     * @param array<PropertyMetadata> $properties
     */
    private function makeContext(array $properties = []): GenerationContext
    {
        return new GenerationContext(
            className: \stdClass::class,
            shortName: 'stdClass',
            hasUndefinable: false,
            properties: $properties,
        );
    }

    private function makeListProperty(
        string $name,
        string $valueType,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinableOnDehydrationWhen = null,
        ?ListOf $listOf = null,
    ): PropertyMetadata {
        $listOf ??= new ListOf($valueType);

        return $this->makeProperty(
            name: $name,
            types: ['array'],
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: true,
            isUndefinable: $isUndefinable,
            undefinableOnDehydrationWhen: $undefinableOnDehydrationWhen,
            attributes: [ListOf::class => $listOf]
        );
    }

    private function makeMapProperty(
        string $name,
        string $valueType,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinableOnDehydrationWhen = null,
        ?MapOf $mapOf = null,
    ): PropertyMetadata {
        $mapOf ??= new MapOf(value: $valueType);

        return $this->makeProperty(
            name: $name,
            types: ['array'],
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: true,
            isUndefinable: $isUndefinable,
            undefinableOnDehydrationWhen: $undefinableOnDehydrationWhen,
            attributes: [MapOf::class => $mapOf]
        );
    }

    /**
     * @param array<string>         $types
     * @param array<string, object> $attributes
     */
    private function makeProperty(
        string $name,
        array $types,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isBuiltin = false,
        bool $hasAttributeDefault = false,
        mixed $attributeDefaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinableOnDehydrationWhen = null,
        array $attributes = [],
    ): PropertyMetadata {
        return new PropertyMetadata(
            name: $name,
            types: $types,
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: $isBuiltin,
            hasAttributeDefault: $hasAttributeDefault,
            attributeDefaultValue: $attributeDefaultValue,
            isUndefinable: $isUndefinable,
            undefinableOnDehydrationWhen: $undefinableOnDehydrationWhen,
            attributes: $attributes,
        );
    }
}
