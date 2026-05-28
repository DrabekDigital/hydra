<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string is not empty (shorthand for LengthRange(min: 1)).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class NonEmptyString implements TypedValidationAttribute
{
    public function __construct(
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {}

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::String];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
