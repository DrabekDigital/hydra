<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Metadata;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\TypedValidationAttribute;
use DrabekDigital\Hydra\Attributes\Validation\ValidationValueType;

final class ValidationAttributeCompatibility
{
    public static function assertCompatible(PropertyMetadata $property): void
    {
        foreach ($property->attributes as $attribute) {
            if (!$attribute instanceof TypedValidationAttribute) {
                continue;
            }

            self::assertPropertyCompatibleWithAttribute($property, $attribute);
        }
    }

    private static function assertPropertyCompatibleWithAttribute(
        PropertyMetadata $property,
        TypedValidationAttribute $attribute,
    ): void {
        if ($property->types === []) {
            return;
        }

        foreach ($property->types as $declaredType) {
            if ($declaredType === 'mixed' && !in_array(ValidationValueType::Mixed, $attribute->supportedValueTypes(), true)) {
                throw new \InvalidArgumentException(sprintf(
                    "Property '%s' is mixed but uses typed validation attribute '%s'; declare a concrete property type.",
                    $property->name,
                    $attribute::class,
                ));
            }

            if (!self::isDeclaredTypeCompatible($declaredType, $attribute)) {
                throw new \InvalidArgumentException(sprintf(
                    "Validation attribute '%s' on property '%s' is incompatible with declared type '%s'.",
                    $attribute::class,
                    $property->name,
                    $declaredType,
                ));
            }
        }
    }

    private static function isDeclaredTypeCompatible(
        string $declaredType,
        TypedValidationAttribute $attribute,
    ): bool {
        foreach ($attribute->supportedValueTypes() as $supportedType) {
            if (self::declaredTypeMatches($declaredType, $supportedType, $attribute)) {
                return true;
            }
        }

        return false;
    }

    private static function declaredTypeMatches(
        string $declaredType,
        ValidationValueType $supportedType,
        TypedValidationAttribute $attribute,
    ): bool {
        return match ($supportedType) {
            ValidationValueType::String => $declaredType === 'string',
            ValidationValueType::Int => $declaredType === 'int',
            ValidationValueType::Float => $declaredType === 'int' || $declaredType === 'float',
            ValidationValueType::Array => $declaredType === 'array',
            ValidationValueType::DateTimeInterface => self::isDateTimeType($declaredType),
            ValidationValueType::Enum => self::isEnumTypeCompatible($declaredType, $attribute),
            ValidationValueType::Mixed => true,
        };
    }

    private static function isDateTimeType(string $declaredType): bool
    {
        return $declaredType === 'DateTimeInterface'
            || $declaredType === 'DateTimeImmutable'
            || $declaredType === 'DateTime'
            || (class_exists($declaredType) && is_a($declaredType, \DateTimeInterface::class, true));
    }

    private static function isEnumTypeCompatible(string $declaredType, TypedValidationAttribute $attribute): bool
    {
        if (!$attribute instanceof Choice || $attribute->enumClass === null) {
            return enum_exists($declaredType);
        }

        if ($declaredType === $attribute->enumClass || is_a($declaredType, $attribute->enumClass, true)) {
            return true;
        }

        return self::isBackedEnumValueType($attribute->enumClass, $declaredType);
    }

    private static function isBackedEnumValueType(string $enumClass, string $declaredType): bool
    {
        if (!enum_exists($enumClass)) {
            return false;
        }

        $reflection = new \ReflectionEnum($enumClass);
        if (!$reflection->isBacked()) {
            return false;
        }

        $backingType = $reflection->getBackingType();
        if (!$backingType instanceof \ReflectionNamedType) {
            return false;
        }

        return $backingType->getName() === $declaredType;
    }
}
