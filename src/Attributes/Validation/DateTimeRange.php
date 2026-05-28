<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a date/time falls within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class DateTimeRange implements TypedValidationAttribute
{
    public function __construct(
        public readonly ?\DateTimeInterface $min = null,
        public readonly ?\DateTimeInterface $max = null,
        public readonly string $bounds = '[]',
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {
        RangeBounds::assertValid($bounds);
        RangeBounds::assertOrderedRange($min, $max, $bounds);
    }

    public function isMinInclusive(): bool
    {
        return str_starts_with($this->bounds, '[');
    }

    public function isMaxInclusive(): bool
    {
        return str_ends_with($this->bounds, ']');
    }

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::DateTimeInterface];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
