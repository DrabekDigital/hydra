<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\NullValidationBehavior;
use DrabekDigital\Hydra\Attributes\Validation\TypedValidationAttribute;
use DrabekDigital\Hydra\Attributes\Validation\ValidationValueType;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class ValidationRuleExecution
{
    public static function resolve(
        TypedValidationAttribute $attribute,
        mixed $value,
    ): ValidationRuleExecutionResult {
        if ($value === null) {
            return match ($attribute->nullBehavior()) {
                NullValidationBehavior::SkipWhenNull => ValidationRuleExecutionResult::Skip,
                NullValidationBehavior::FailWhenNull => ValidationRuleExecutionResult::NullFailure,
                NullValidationBehavior::ValidateAlways => self::nullValueIsApplicable($attribute)
                    ? ValidationRuleExecutionResult::Apply
                    : ValidationRuleExecutionResult::TypeMismatch,
            };
        }

        if (!self::valueMatchesSupportedTypes($value, $attribute)) {
            return ValidationRuleExecutionResult::TypeMismatch;
        }

        return ValidationRuleExecutionResult::Apply;
    }

    public static function nullFailureError(
        TypedValidationAttribute $attribute,
        string $key,
        string $fullPath,
    ): ValidationError {
        return new ValidationError(
            code: ValidationErrorCode::NOT_NULL,
            programmerMessage: "Value '{$key}' must not be null for ".self::attributeLabel($attribute),
            key: $key,
            fullPath: $fullPath,
        );
    }

    public static function typeMismatchError(string $key, string $fullPath, TypedValidationAttribute $attribute): ValidationError
    {
        return new ValidationError(
            code: ValidationErrorCode::RULE_NOT_APPLICABLE,
            programmerMessage: "Value '{$key}' has incompatible type for ".self::attributeLabel($attribute),
            key: $key,
            fullPath: $fullPath,
        );
    }

    public static function isApplicable(TypedValidationAttribute $attribute, mixed $value): bool
    {
        if ($value === null) {
            return self::nullValueIsApplicable($attribute);
        }

        return self::valueMatchesSupportedTypes($value, $attribute);
    }

    private static function attributeLabel(TypedValidationAttribute $attribute): string
    {
        return $attribute::class;
    }

    private static function nullValueIsApplicable(TypedValidationAttribute $attribute): bool
    {
        foreach ($attribute->supportedValueTypes() as $supportedType) {
            if ($supportedType === ValidationValueType::Mixed) {
                return true;
            }
        }

        return false;
    }

    private static function valueMatchesSupportedTypes(mixed $value, TypedValidationAttribute $attribute): bool
    {
        foreach ($attribute->supportedValueTypes() as $supportedType) {
            if (self::valueMatchesType($value, $supportedType, $attribute)) {
                return true;
            }
        }

        return false;
    }

    private static function valueMatchesType(
        mixed $value,
        ValidationValueType $type,
        TypedValidationAttribute $attribute,
    ): bool {
        return match ($type) {
            ValidationValueType::String => is_string($value),
            ValidationValueType::Int => is_int($value),
            ValidationValueType::Float => is_int($value) || is_float($value),
            ValidationValueType::Array => is_array($value),
            ValidationValueType::DateTimeInterface => $value instanceof \DateTimeInterface,
            ValidationValueType::Enum => self::valueMatchesEnum($value, $attribute),
            ValidationValueType::Mixed => true,
        };
    }

    private static function valueMatchesEnum(mixed $value, TypedValidationAttribute $attribute): bool
    {
        if ($value instanceof \UnitEnum) {
            return true;
        }

        if (!$attribute instanceof Choice || $attribute->enumClass === null) {
            return false;
        }

        if (!enum_exists($attribute->enumClass)) {
            return false;
        }

        $reflection = new \ReflectionEnum($attribute->enumClass);
        if (!$reflection->isBacked()) {
            return false;
        }

        $backingType = $reflection->getBackingType();
        if (!$backingType instanceof \ReflectionNamedType) {
            return false;
        }

        return match ($backingType->getName()) {
            'string' => is_string($value),
            'int' => is_int($value),
            default => false,
        };
    }
}
