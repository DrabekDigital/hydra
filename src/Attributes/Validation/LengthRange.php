<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string length falls within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class LengthRange implements TypedValidationAttribute
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {
        RangeBounds::assertInclusiveIntRange($min, $max);
    }

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::String];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
