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
     * @param string                           $key               The key type ('string' or 'int')
     * @param class-string|string              $value             The value type (e.g., Person::class or 'string')
     * @param null|string                      $discriminator     Field name in values used for polymorphic dispatch
     * @param null|array<string, class-string> $mapping           Map of discriminator values to class names
     * @param bool                             $passDiscriminator Whether to pass the discriminator to subtype payload (default: true)
     */
    public function __construct(
        public readonly string $key = 'string',
        public readonly string $value = 'mixed',
        public readonly ?string $discriminator = null,
        public readonly ?array $mapping = null,
        public readonly bool $passDiscriminator = true,
    ) {}
}
