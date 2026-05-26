<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that an array contains only unique items.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class UniqueItems
{
    /**
     * @param null|\Closure $comparator Custom comparator function (a, b) => bool
     */
    public function __construct(
        public readonly ?\Closure $comparator = null,
    ) {}
}
