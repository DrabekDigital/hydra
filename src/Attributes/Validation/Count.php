<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that an array has a count within a specified range.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Count
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {}
}
