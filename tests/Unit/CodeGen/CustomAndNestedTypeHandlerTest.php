<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\CustomFormatterTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\CustomMapperTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\NestedObjectTypeHandler;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\AddressDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\AsMoney;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\MoneyMapper;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CustomAndNestedTypeHandlerTest extends TestCase
{
    public function testCustomFormatterSupportsOnlyWhenAttributePresent(): void
    {
        $handler = new CustomFormatterTypeHandler();
        $context = $this->makeContext();

        $supported = $this->makeProperty(
            name: 'title',
            types: ['string'],
            attributes: [CustomFormatter::class => new CustomFormatter()]
        );
        $unsupported = $this->makeProperty(name: 'title', types: ['string']);

        $this->assertTrue($handler->supports($supported, $context));
        $this->assertFalse($handler->supports($unsupported, $context));
    }

    public function testCustomFormatterGenerateHydrationRequiredBranch(): void
    {
        $handler = new CustomFormatterTypeHandler();
        $property = $this->makeFormatterProperty(name: 'title');

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomFormatterGenerateHydrationNullableRequiredBranch(): void
    {
        $handler = new CustomFormatterTypeHandler();
        $property = $this->makeFormatterProperty(name: 'title', isNullable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomFormatterGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new CustomFormatterTypeHandler();
        $property = $this->makeFormatterProperty(
            name: 'title',
            isNullable: true,
            hasDefault: true,
            defaultValue: null
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomFormatterGenerateHydrationUndefinableBranch(): void
    {
        $handler = new CustomFormatterTypeHandler();
        $property = $this->makeFormatterProperty(name: 'title', isUndefinable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomFormatterGenerateDehydrationMarksContextAndCoversBranches(): void
    {
        $handler = new CustomFormatterTypeHandler();

        $simple = $this->makeFormatterProperty(name: 'title');
        $nullable = $this->makeFormatterProperty(name: 'title', isNullable: true);
        $undef = $this->makeFormatterProperty(name: 'title', isUndefinable: true);
        $undefFallback = $this->makeFormatterProperty(
            name: 'title',
            isUndefinable: true,
            undefinedDehydrationValue: 'missing'
        );

        $context = $this->makeContext([$simple, $nullable, $undef, $undefFallback]);

        $this->assertNotEmpty($handler->generateDehydration($simple, $context));
        $this->assertTrue($context->dehydrationErrorsGenerated());
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
        $this->assertNotEmpty($handler->generateDehydration($undef, $this->makeContext([$undef])));
        $this->assertNotEmpty($handler->generateDehydration($undefFallback, $this->makeContext([$undefFallback])));
    }

    public function testCustomMapperSupportsOnlyWhenMapperAttributePresent(): void
    {
        $handler = new CustomMapperTypeHandler();
        $context = $this->makeContext();

        $supported = $this->makeProperty(
            name: 'price',
            types: ['int'],
            attributes: [AsMoney::class => new AsMoney()]
        );
        $unsupported = $this->makeProperty(name: 'price', types: ['int']);

        $this->assertTrue($handler->supports($supported, $context));
        $this->assertFalse($handler->supports($unsupported, $context));
    }

    public function testCustomMapperGenerateHydrationThrowsWhenMapperAttributeMissing(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeProperty(name: 'price', types: ['int']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testCustomMapperGenerateDehydrationThrowsWhenMapperAttributeMissing(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeProperty(name: 'price', types: ['int']);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testCustomMapperGenerateHydrationRequiredAndRegistersMapperType(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeMapperProperty(name: 'price');
        $context = $this->makeContext([$property]);

        $this->assertNotEmpty($handler->generateHydration($property, $context));
        $this->assertContains(MoneyMapper::class, $context->getNestedTypes());
    }

    public function testCustomMapperGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeMapperProperty(name: 'price', isNullable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomMapperGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeMapperProperty(
            name: 'price',
            isNullable: true,
            hasDefault: true,
            defaultValue: null
        );

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomMapperGenerateHydrationUndefinableBranch(): void
    {
        $handler = new CustomMapperTypeHandler();
        $property = $this->makeMapperProperty(name: 'price', isUndefinable: true);

        $this->assertNotEmpty($handler->generateHydration($property, $this->makeContext([$property])));
    }

    public function testCustomMapperGenerateDehydrationMarksContextAndCoversBranches(): void
    {
        $handler = new CustomMapperTypeHandler();

        $simple = $this->makeMapperProperty(name: 'price');
        $nullable = $this->makeMapperProperty(name: 'price', isNullable: true);
        $undef = $this->makeMapperProperty(name: 'price', isUndefinable: true);
        $undefFallback = $this->makeMapperProperty(
            name: 'price',
            isUndefinable: true,
            undefinedDehydrationValue: 0
        );

        $context = $this->makeContext([$simple, $nullable, $undef, $undefFallback]);

        $this->assertNotEmpty($handler->generateDehydration($simple, $context));
        $this->assertTrue($context->dehydrationErrorsGenerated());
        $this->assertContains(MoneyMapper::class, $context->getNestedTypes());
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
        $this->assertNotEmpty($handler->generateDehydration($undef, $this->makeContext([$undef])));
        $this->assertNotEmpty($handler->generateDehydration($undefFallback, $this->makeContext([$undefFallback])));
    }

    public function testNestedObjectSupportsOnlyExistingObjectTypes(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());
        $context = $this->makeContext();

        $this->assertTrue($handler->supports($this->makeProperty(name: 'address', types: [AddressDTO::class]), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'age', types: ['int']), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'unknown', types: ['Not\Existing\Class']), $context));
    }

    public function testNestedObjectGenerateHydrationThrowsWhenTypeMissing(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'address', types: []);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testNestedObjectGenerateDehydrationThrowsWhenTypeMissing(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'address', types: []);

        $this->expectException(\InvalidArgumentException::class);
        $handler->generateDehydration($property, $this->makeContext([$property]));
    }

    public function testNestedObjectRejectsUnitEnums(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());
        $property = $this->makeProperty(name: 'kind', types: [HandlerUnitEnum::class]);

        $this->expectException(\LogicException::class);
        $handler->generateHydration($property, $this->makeContext([$property]));
    }

    public function testNestedObjectGenerateHydrationBranchesAndRegistersNestedType(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());

        $required = $this->makeProperty(name: 'address', types: [AddressDTO::class]);
        $nullableRequired = $this->makeProperty(name: 'address', types: [AddressDTO::class], isNullable: true);
        $withDefault = $this->makeProperty(
            name: 'address',
            types: [AddressDTO::class],
            isNullable: true,
            hasDefault: true,
            defaultValue: null
        );
        $undef = $this->makeProperty(name: 'address', types: [AddressDTO::class], isUndefinable: true);

        $context = $this->makeContext([$required, $nullableRequired, $withDefault, $undef]);

        $this->assertNotEmpty($handler->generateHydration($required, $context));
        $this->assertContains(AddressDTO::class, $context->getNestedTypes());
        $this->assertNotEmpty($handler->generateHydration($nullableRequired, $this->makeContext([$nullableRequired])));
        $this->assertNotEmpty($handler->generateHydration($withDefault, $this->makeContext([$withDefault])));
        $this->assertNotEmpty($handler->generateHydration($undef, $this->makeContext([$undef])));
    }

    public function testNestedObjectGenerateDehydrationBranchesAndFallback(): void
    {
        $handler = new NestedObjectTypeHandler(new ClassAnalyzer());

        $simple = $this->makeProperty(name: 'address', types: [AddressDTO::class]);
        $nullable = $this->makeProperty(name: 'address', types: [AddressDTO::class], isNullable: true);
        $undef = $this->makeProperty(name: 'address', types: [AddressDTO::class], isUndefinable: true);
        $undefFallback = $this->makeProperty(
            name: 'address',
            types: [AddressDTO::class],
            isUndefinable: true,
            undefinedDehydrationValue: []
        );

        $context = $this->makeContext([$simple, $nullable, $undef, $undefFallback]);

        $this->assertNotEmpty($handler->generateDehydration($simple, $context));
        $this->assertContains(AddressDTO::class, $context->getNestedTypes());
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
        $this->assertNotEmpty($handler->generateDehydration($undef, $this->makeContext([$undef])));
        $this->assertNotEmpty($handler->generateDehydration($undefFallback, $this->makeContext([$undefFallback])));
    }

    private function makeFormatterProperty(
        string $name,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinedDehydrationValue = null,
    ): PropertyMetadata {
        return $this->makeProperty(
            name: $name,
            types: ['string'],
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: true,
            isUndefinable: $isUndefinable,
            undefinedDehydrationValue: $undefinedDehydrationValue,
            attributes: [
                CustomFormatter::class => new CustomFormatter(
                    hydrate: static fn (mixed $value): mixed => $value,
                    dehydrate: static fn (mixed $value): mixed => $value
                ),
            ]
        );
    }

    private function makeMapperProperty(
        string $name,
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinedDehydrationValue = null,
    ): PropertyMetadata {
        return $this->makeProperty(
            name: $name,
            types: ['int'],
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: true,
            isUndefinable: $isUndefinable,
            undefinedDehydrationValue: $undefinedDehydrationValue,
            attributes: [AsMoney::class => new AsMoney()]
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

enum HandlerUnitEnum
{
    case Foo;
}
