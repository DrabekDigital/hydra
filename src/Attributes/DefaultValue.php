<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Specifies a default value for a property when the key is missing from input.
 *
 * Use this when you cannot use PHP's default parameter syntax due to
 * "Optional parameter declared before required parameter" error.
 *
 * On dehydration, the value will be persisted normally.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
readonly class DefaultValue
{
    public function __construct(
        public mixed $value,
    ) {}
}
