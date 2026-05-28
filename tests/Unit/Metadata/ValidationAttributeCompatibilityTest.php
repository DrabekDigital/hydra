<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Metadata;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Metadata\ValidationAttributeCompatibility;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationAdditionalRulesDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationEnumPropertyChoiceDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleAttributeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleCountDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationIncompatibleNumberRangeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationMixedPropertyEmailDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\ValidationTypedRulesDTO;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ValidationAttributeCompatibilityTest extends TestCase
{
    private ClassAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new ClassAnalyzer();
    }

    public function testAllPropertiesOnTypedRulesDtoAreCompatible(): void
    {
        foreach ($this->analyzer->analyze(ValidationTypedRulesDTO::class) as $property) {
            ValidationAttributeCompatibility::assertCompatible($property);
        }

        $this->addToAssertionCount(1);
    }

    public function testEmailOnIntPropertyThrows(): void
    {
        $property = $this->property(ValidationIncompatibleAttributeDTO::class, 'age');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Email' on property 'age'");

        ValidationAttributeCompatibility::assertCompatible($property);
    }

    public function testCountOnStringPropertyThrows(): void
    {
        $property = $this->property(ValidationIncompatibleCountDTO::class, 'label');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Count' on property 'label'");

        ValidationAttributeCompatibility::assertCompatible($property);
    }

    public function testNumberRangeOnStringPropertyThrows(): void
    {
        $property = $this->property(ValidationIncompatibleNumberRangeDTO::class, 'quantity');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("NumberRange' on property 'quantity'");

        ValidationAttributeCompatibility::assertCompatible($property);
    }

    public function testChoiceEnumClassOnEnumPropertyPasses(): void
    {
        ValidationAttributeCompatibility::assertCompatible(
            $this->property(ValidationEnumPropertyChoiceDTO::class, 'status'),
        );
        $this->addToAssertionCount(1);
    }

    public function testChoiceEnumClassOnStringPropertyPassesForBackedEnum(): void
    {
        ValidationAttributeCompatibility::assertCompatible(
            $this->property(ValidationAdditionalRulesDTO::class, 'status'),
        );
        $this->addToAssertionCount(1);
    }

    public function testEmailOnEnumPropertyWithoutChoiceThrows(): void
    {
        $metadata = new PropertyMetadata(
            name: 'status',
            types: [Status::class],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: false,
            attributes: [Email::class => new Email()],
        );

        $this->expectException(\InvalidArgumentException::class);
        ValidationAttributeCompatibility::assertCompatible($metadata);
    }

    public function testMixedPropertyWithTypedAttributeThrows(): void
    {
        $property = $this->property(ValidationMixedPropertyEmailDTO::class, 'contact');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mixed');

        ValidationAttributeCompatibility::assertCompatible($property);
    }

    public function testNullableStringWithEmailPasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'email',
            types: ['string'],
            isNullable: true,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [Email::class => new Email()],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testIntPropertyWithNumberRangePasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'age',
            types: ['int'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [NumberRange::class => new NumberRange(min: 0, max: 120)],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testFloatPropertyWithNumberRangePasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'score',
            types: ['float'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [NumberRange::class => new NumberRange(min: 0.0, max: 1.0)],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testArrayPropertyWithCountAndUniqueItemsPasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'tags',
            types: ['array'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [
                Count::class => new Count(min: 1),
                UniqueItems::class => new UniqueItems(),
            ],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testDateTimeImmutableWithDateTimeRangePasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'createdAt',
            types: ['DateTimeImmutable'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: false,
            attributes: [DateTimeRange::class => new DateTimeRange()],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testStringPropertyWithLengthRangePasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'name',
            types: ['string'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [LengthRange::class => new LengthRange(min: 1)],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    public function testChoiceWithScalarChoicesOnStringPropertyPasses(): void
    {
        $metadata = new PropertyMetadata(
            name: 'color',
            types: ['string'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: [Choice::class => new Choice(['red', 'green'])],
        );

        ValidationAttributeCompatibility::assertCompatible($metadata);
        $this->addToAssertionCount(1);
    }

    /**
     * @param class-string $class
     */
    private function property(string $class, string $name): PropertyMetadata
    {
        foreach ($this->analyzer->analyze($class) as $property) {
            if ($property->name === $name) {
                return $property;
            }
        }

        $this->fail("Property '{$name}' not found on {$class}");
    }
}
