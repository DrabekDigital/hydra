<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property as undefinable - it can remain uninitialized.
 *
 * On hydration: If the key is missing, the property stays uninitialized.
 * On dehydration: If uninitialized, behavior is controlled by explicit policy.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
readonly class Undefinable
{
    public function __construct(
        public UndefinedDehydration $onUndefinedDehydration = UndefinedDehydration::SKIP,
        public mixed $undefinedAs = null,
    ) {}
}
