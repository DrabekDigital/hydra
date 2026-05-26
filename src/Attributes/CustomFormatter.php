<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Lightweight custom formatter using closures.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class CustomFormatter
{
    public function __construct(
        public readonly ?\Closure $hydrate = null,
        public readonly ?\Closure $dehydrate = null,
    ) {}
}
