<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property as undefinable - it can remain uninitialized.
 *
 * On hydration: If the key is missing, the property stays uninitialized.
 * On dehydration: If uninitialized, the key is skipped OR set to onDehydrationWhen value.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
readonly class Undefinable
{
    public function __construct(
        public mixed $onDehydrationWhen = null,
    ) {}
}
