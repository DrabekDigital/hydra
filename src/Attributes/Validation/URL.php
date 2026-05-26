<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string is a valid URL.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class URL
{
    /**
     * @param null|list<string> $allowedSchemes
     */
    public function __construct(
        public readonly bool $requireScheme = true,
        public readonly ?array $allowedSchemes = ['http', 'https'],
    ) {}
}
