<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\Attributes\DateTime;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\DateTimeTypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\DateTimeZoneTypeHandler;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DateAndZoneTypeHandlerTest extends TestCase
{
    public function testDateTimeSupportsDateTimeInterfaceTypes(): void
    {
        $handler = new DateTimeTypeHandler();
        $context = $this->makeContext();

        $this->assertTrue($handler->supports($this->makeProperty(name: 'dt', types: [\DateTimeImmutable::class]), $context));
        $this->assertTrue($handler->supports($this->makeProperty(name: 'dt', types: [\DateTime::class]), $context));
    }

    public function testDateTimeDoesNotSupportMissingType(): void
    {
        $handler = new DateTimeTypeHandler();
        $context = $this->makeContext();

        $this->assertFalse($handler->supports($this->makeProperty(name: 'dt', types: []), $context));
    }

    public function testDateTimeGenerateHydrationRequiredBranch(): void
    {
        $handler = new DateTimeTypeHandler();
        $property = $this->makeProperty(
            name: 'createdAt',
            types: [\DateTimeImmutable::class],
            attributes: [DateTime::class => new DateTime(format: 'Y-m-d')]
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new DateTimeTypeHandler();
        $property = $this->makeProperty(
            name: 'createdAt',
            types: [\DateTimeImmutable::class],
            isNullable: true,
            attributes: [DateTime::class => new DateTime(format: 'Y-m-d')]
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new DateTimeTypeHandler();
        $property = $this->makeProperty(
            name: 'createdAt',
            types: [\DateTimeImmutable::class],
            isNullable: true,
            hasDefault: true,
            defaultValue: null,
            attributes: [DateTime::class => new DateTime(format: 'Y-m-d')]
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeGenerateHydrationUndefinableBranch(): void
    {
        $handler = new DateTimeTypeHandler();
        $property = $this->makeProperty(
            name: 'createdAt',
            types: [\DateTimeImmutable::class],
            isUndefinable: true,
            attributes: [DateTime::class => new DateTime(format: 'Y-m-d')]
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeGenerateHydrationUsesMutableClassWhenConfigured(): void
    {
        $handler = new DateTimeTypeHandler();
        $property = $this->makeProperty(
            name: 'createdAt',
            types: [\DateTime::class],
            attributes: [DateTime::class => new DateTime(format: 'Y-m-d H:i:s', immutable: false)]
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeGenerateDehydrationSimpleAndNullableBranches(): void
    {
        $handler = new DateTimeTypeHandler();

        $simple = $this->makeProperty(name: 'createdAt', types: [\DateTimeImmutable::class]);
        $nullable = $this->makeProperty(name: 'updatedAt', types: [\DateTimeImmutable::class], isNullable: true);

        $this->assertNotEmpty($handler->generateDehydration($simple, $this->makeContext([$simple])));
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
    }

    public function testDateTimeGenerateDehydrationUndefinableWithAndWithoutFallback(): void
    {
        $handler = new DateTimeTypeHandler();

        $withoutFallback = $this->makeProperty(
            name: 'publishedAt',
            types: [\DateTimeImmutable::class],
            isUndefinable: true
        );
        $withFallback = $this->makeProperty(
            name: 'archivedAt',
            types: [\DateTimeImmutable::class],
            isUndefinable: true,
            undefinedDehydrationValue: 'missing'
        );

        $this->assertNotEmpty($handler->generateDehydration($withoutFallback, $this->makeContext([$withoutFallback])));
        $this->assertNotEmpty($handler->generateDehydration($withFallback, $this->makeContext([$withFallback])));
    }

    public function testDateTimeZoneSupportsDateTimeZoneTypeOnly(): void
    {
        $handler = new DateTimeZoneTypeHandler();
        $context = $this->makeContext();

        $this->assertTrue($handler->supports($this->makeProperty(name: 'tz', types: [\DateTimeZone::class]), $context));
        $this->assertFalse($handler->supports($this->makeProperty(name: 'tz', types: ['string']), $context));
    }

    public function testDateTimeZoneGenerateHydrationRequiredBranch(): void
    {
        $handler = new DateTimeZoneTypeHandler();
        $property = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class]);

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeZoneGenerateHydrationNullableRequiredKeyBranch(): void
    {
        $handler = new DateTimeZoneTypeHandler();
        $property = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class], isNullable: true);

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeZoneGenerateHydrationWithDefaultBranch(): void
    {
        $handler = new DateTimeZoneTypeHandler();
        $property = $this->makeProperty(
            name: 'tz',
            types: [\DateTimeZone::class],
            isNullable: true,
            hasDefault: true,
            defaultValue: null
        );

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeZoneGenerateHydrationUndefinableBranch(): void
    {
        $handler = new DateTimeZoneTypeHandler();
        $property = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class], isUndefinable: true);

        $stmts = $handler->generateHydration($property, $this->makeContext([$property]));
        $this->assertNotEmpty($stmts);
    }

    public function testDateTimeZoneGenerateDehydrationSimpleAndNullableBranches(): void
    {
        $handler = new DateTimeZoneTypeHandler();

        $simple = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class]);
        $nullable = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class], isNullable: true);

        $this->assertNotEmpty($handler->generateDehydration($simple, $this->makeContext([$simple])));
        $this->assertNotEmpty($handler->generateDehydration($nullable, $this->makeContext([$nullable])));
    }

    public function testDateTimeZoneGenerateDehydrationUndefinableWithAndWithoutFallback(): void
    {
        $handler = new DateTimeZoneTypeHandler();

        $withoutFallback = $this->makeProperty(name: 'tz', types: [\DateTimeZone::class], isUndefinable: true);
        $withFallback = $this->makeProperty(
            name: 'tz',
            types: [\DateTimeZone::class],
            isUndefinable: true,
            undefinedDehydrationValue: 'UTC'
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
