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
}
