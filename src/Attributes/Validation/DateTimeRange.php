<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a date/time falls within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class DateTimeRange
{
    public function __construct(
        public readonly ?\DateTimeInterface $min = null,
        public readonly ?\DateTimeInterface $max = null,
        public readonly string $bounds = '[]',
    ) {
        RangeBounds::assertValid($bounds);
    }

    public function isMinInclusive(): bool
    {
        return str_starts_with($this->bounds, '[');
    }

    public function isMaxInclusive(): bool
    {
        return str_ends_with($this->bounds, ']');
    }
}
