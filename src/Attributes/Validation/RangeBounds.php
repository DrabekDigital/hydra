<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Interval notation for {@see NumberRange} and {@see DateTimeRange} bounds.
 */
final class RangeBounds
{
    /** @var list<string> */
    public const VALID_NOTATIONS = ['[]', '()', '[)', '(]'];

    public static function assertValid(string $bounds): void
    {
        if (!in_array($bounds, self::VALID_NOTATIONS, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid bounds notation "%s"; expected one of: %s',
                $bounds,
                implode(', ', self::VALID_NOTATIONS),
            ));
        }
    }

    public static function assertInclusiveIntRange(?int $min, ?int $max): void
    {
        if ($min !== null && $max !== null && $min > $max) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid range: min (%d) must not be greater than max (%d)',
                $min,
                $max,
            ));
        }
    }

    public static function assertOrderedRange(
        \DateTimeInterface|float|int|null $min,
        \DateTimeInterface|float|int|null $max,
        string $bounds,
    ): void {
        if ($min === null || $max === null) {
            return;
        }

        $comparison = $min <=> $max;

        if ($comparison > 0) {
            throw new \InvalidArgumentException('Invalid range: min must not be greater than max');
        }

        if ($comparison === 0 && $bounds !== '[]') {
            throw new \InvalidArgumentException(sprintf(
                'Invalid range: min equals max with bounds "%s" defines an empty interval',
                $bounds,
            ));
        }
    }
}
