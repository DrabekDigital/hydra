<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string matches a regex pattern.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class StringMatches
{
    public function __construct(
        public readonly string $pattern,
        public readonly ?string $message = null,
    ) {}
}
