<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a number falls within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class NumberRange implements TypedValidationAttribute
{
    /**
     * @param null|float|int $min    Minimum value
     * @param null|float|int $max    Maximum value
     * @param string         $bounds Bounds notation: '[]' inclusive, '()' exclusive, '[)' or '(]' mixed
     */
    public function __construct(
        public readonly float|int|null $min = null,
        public readonly float|int|null $max = null,
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
        return [ValidationValueType::Int, ValidationValueType::Float];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
