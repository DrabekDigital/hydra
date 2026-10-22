<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\BackedEnumTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\ObjectTypeHandler;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class EnumAndObjectTypeHandlerTest extends TestCase
{
    public function testBackedEnumSupportsBackedEnumsOnly(): void
    {
        $handler = new BackedEnumTypeHandler();
        $context = $this->makeContext();

        $this->assertTrue($handler->supports($this->makeProperty(name: 'status', types: [Status::class]), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'kind', types: [LocalUnitEnum::class]), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'plain', types: ['string']), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'missing', types: []), $context));
    }

    public function testBackedEnumGenerateHydrationThrowsWhenTypeMissing(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(name: 'status', types: []);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testBackedEnumGenerateHydrationThrowsWhenEnumClassDoesNotExist(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(name: 'status', types: ['Not\Existing\Enum']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testBackedEnumGenerateHydrationRequiredIntAndStringBranches(): void
    {
        $handler = new BackedEnumTypeHandler();
        $status = $this->makeProperty(name: 'status', types: [Status::class]);
        $priority = $this->makeProperty(name: 'priority', types: [Priority::class]);

        $statusContext = $this->makeContext([$status]);
        $priorityContext = $this->makeContext([$priority]);

        $this->assertNotEmpty($handler->generateHydration($status, $statusContext));
        $this->assertNotEmpty($handler->generateHydration($priority, $priorityContext));
        $this->assertContains(Status::class, $statusContext->getNestedTypes());
        $this->assertContains(Priority::class, $priorityContext->getNestedTypes());
    }

    public function testBackedEnumGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(name: 'status', types: [Status::class], isNullable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testBackedEnumGenerateHydrationWithDefaultBackedEnumBranch(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(
            name: 'status',
            types: [Status::class],
            hasDefault: true,
            defaultValue: Status::Draft
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testBackedEnumGenerateHydrationWithDefaultNullableBranch(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(
            name: 'status',
            types: [Status::class],
            isNullable: true,
            hasDefault: true,
            defaultValue: null
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testBackedEnumGenerateHydrationUndefinableBranch(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(name: 'status', types: [Status::class], isUndefinable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testBackedEnumGenerateDehydrationThrowsWhenTypeMissing(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(name: 'status', types: []);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testBackedEnumGenerateDehydrationSimpleNullableAndUndefinableBranches(): void
    {
        $handler = new BackedEnumTypeHandler();

        $simple = $this->makeProperty(name: 'status', types: [Status::class]);
        $nullable = $this->makeProperty(name: 'status', types: [Status::class], isNullable: true);
        $undef = $this->makeProperty(name: 'status', types: [Status::class], isUndefinable: true);
        $undefFallback = $this->makeProperty(
            name: 'status',
            types: [Status::class],
            isUndefinable: true,
            undefinedDehydrationValue: 'draft'
        );

        $this->assertNotEmpty($handler->generateDehydration($simple, $this->makeContext([$simple])));
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
        $this->assertNotEmpty($handler->generateDehydration($undef, $this->makeContext([$undef])));
        $this->assertNotEmpty($handler->generateDehydration($undefFallback, $this->makeContext([$undefFallback])));
    }

    public function testBackedEnumGenerateDehydrationUndefinableNullableBranch(): void
    {
        $handler = new BackedEnumTypeHandler();
        $property = $this->makeProperty(
            name: 'status',
            types: [Status::class],
            isNullable: true,
            isUndefinable: true
        );

        $this->assertNotEmpty($handler->generateDehydration($property, $this->makeContext([$property])));
    }

    public function testObjectTypeSupportsOnlyWhenAttributePresent(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $context = $this->makeContext();

        $supported = $this->makeProperty(
            name: 'featured',
            types: [Vehicle::class],
            attributes: [ObjectType::class => new ObjectType()]
        );
        $unsupported = $this->makeProperty(name: 'featured', types: [Vehicle::class]);

        $this->assertTrue($handler->supports($supported, $context));
        $this->assertFalse($handler->supports($unsupported, $context));
    }

    public function testObjectTypeGenerateHydrationSimpleRequiredAndRegistersBaseType(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            objectType: new ObjectType(value: AddressDTO::class)
        );
        $context = $this->makeContext([$property]);

        $this->assertNotEmpty($handler->generateHydration($property, $context));
        $this->assertContains(AddressDTO::class, $context->getNestedTypes());
    }

    public function testObjectTypeGenerateHydrationDiscriminatorBranchesRegisterMappingTypes(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(
                discriminator: 'type',
                mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class],
                passDiscriminator: false
            )
        );
        $context = $this->makeContext([$property]);

        $this->assertNotEmpty($handler->generateHydration($property, $context));
        $nested = $context->getNestedTypes();
        $this->assertContains(Vehicle::class, $nested);
        $this->assertContains(Car::class, $nested);
        $this->assertContains(Motorcycle::class, $nested);
    }

    public function testObjectTypeGenerateHydrationNullableRequiredKeyAndDefaultBranches(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $nullableRequired = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            isNullable: true,
            objectType: new ObjectType(value: AddressDTO::class)
        );
        $withDefault = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            isNullable: true,
            hasDefault: true,
            defaultValue: null,
            objectType: new ObjectType(value: AddressDTO::class)
        );

        $this->assertNotEmpty($handler->generateHydration($nullableRequired, $this->makeContext([$nullableRequired])));
        $this->assertNotEmpty($handler->generateHydration($withDefault, $this->makeContext([$withDefault])));
    }

    public function testObjectTypeGenerateHydrationUndefinableBranch(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            isUndefinable: true,
            objectType: new ObjectType(value: AddressDTO::class)
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testObjectTypeGenerateHydrationDefaultBranchForNonNullableProperty(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            hasDefault: true,
            defaultValue: null,
            objectType: new ObjectType(value: AddressDTO::class)
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testObjectTypeGenerateHydrationThrowsWhenTargetTypeCannotBeResolved(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'mystery',
            primaryType: null,
            objectType: new ObjectType()
        );

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testObjectTypeGenerateHydrationThrowsWhenDiscriminatorMissingMapping(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $discriminatorWithoutMapping = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(discriminator: 'type')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discriminator and mapping must be set together');
        $handler->generateHydration($discriminatorWithoutMapping, $this->makeContext([$discriminatorWithoutMapping]));
    }

    public function testObjectTypeGenerateHydrationThrowsWhenMappingMissingDiscriminator(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $mappingWithoutDiscriminator = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(mapping: ['car' => Car::class])
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discriminator and mapping must be set together');
        $handler->generateHydration($mappingWithoutDiscriminator, $this->makeContext([$mappingWithoutDiscriminator]));
    }

    public function testObjectTypeGenerateHydrationWithNonObjectBaseTypeSkipsRegistration(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'raw',
            primaryType: 'string',
            objectType: new ObjectType()
        );
        $context = $this->makeContext([$property]);

        $this->assertNotEmpty($handler->generateHydration($property, $context));
        $this->assertSame([], $context->getNestedTypes());
    }

    public function testObjectTypeGenerateDehydrationSimpleNullableAndMappingBranches(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $simple = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            objectType: new ObjectType(value: AddressDTO::class)
        );
        $nullable = $this->makeObjectTypeProperty(
            name: 'address',
            primaryType: AddressDTO::class,
            isNullable: true,
            objectType: new ObjectType(value: AddressDTO::class)
        );
        $mapping = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(
                discriminator: 'type',
                mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class],
                passDiscriminator: true
            )
        );

        $this->assertNotEmpty($handler->generateDehydration($simple, $this->makeContext([$simple])));
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
        $this->assertNotEmpty($handler->generateDehydration($mapping, $this->makeContext([$mapping])));
    }

    public function testObjectTypeGenerateDehydrationThrowsWhenTargetTypeCannotBeResolved(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeObjectTypeProperty(
            name: 'mystery',
            primaryType: null,
            objectType: new ObjectType()
        );

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testObjectTypeGenerateDehydrationThrowsWhenDiscriminatorMissingMapping(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $discriminatorWithoutMapping = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(discriminator: 'type')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discriminator and mapping must be set together');
        $handler->generateDehydration($discriminatorWithoutMapping, $this->makeContext([$discriminatorWithoutMapping]));
    }

    public function testObjectTypeGenerateDehydrationThrowsWhenMappingMissingDiscriminator(): void
    {
        $handler = new ObjectTypeHandler(new ClassAnalyzer());

        $mappingWithoutDiscriminator = $this->makeObjectTypeProperty(
            name: 'featured',
            primaryType: Vehicle::class,
            objectType: new ObjectType(mapping: ['car' => Car::class])
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('discriminator and mapping must be set together');
        $handler->generateDehydration($mappingWithoutDiscriminator, $this->makeContext([$mappingWithoutDiscriminator]));
    }

    private function makeObjectTypeProperty(
        string $name,
        ?string $primaryType,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isUndefinable = false,
        ?ObjectType $objectType = null,
    ): PropertyMetadata {
        $types = $primaryType !== null ? [$primaryType] : [];
        $attributes = [ObjectType::class => $objectType ?? new ObjectType()];

        return $this->makeProperty(
            name: $name,
            types: $types,
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: false,
            isUndefinable: $isUndefinable,
            attributes: $attributes
        );
    }

    /**
     * @param array<PropertyMetadata> $properties
     */
    private function makeContext(array $properties = []): GenerationContext
    {
        return new GenerationContext(
            className: \stdClass::class,
            hasUndefinable: false,
            properties: $properties,
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
        mixed $undefinedDehydrationValue = null,
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
            undefinedDehydrationValue: $undefinedDehydrationValue,
            attributes: $attributes,
        );
    }
}

enum LocalUnitEnum
{
    case One;
}
