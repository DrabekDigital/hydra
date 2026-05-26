<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property as a map (associative array) with typed keys and values.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class MapOf
{
    /**
     * @param string              $key   The key type ('string' or 'int')
     * @param class-string|string $value The value type (e.g., Person::class or 'string')
     */
    public function __construct(
        public readonly string $key = 'string',
        public readonly string $value = 'mixed',
    ) {}
}
