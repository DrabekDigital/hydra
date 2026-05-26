<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Skip a property during hydration and/or dehydration.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Skip
{
    public function __construct(
        public readonly bool $hydration = true,
        public readonly bool $dehydration = true,
    ) {}
}
