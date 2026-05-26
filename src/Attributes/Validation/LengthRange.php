<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string length falls within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class LengthRange
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {}
}
