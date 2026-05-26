<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property/parameter as a polymorphic object resolved via discriminator mapping.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ObjectType
{
    /**
     * @param null|string                      $discriminator     Field name in payload used for polymorphic dispatch
     * @param null|array<string, class-string> $mapping           Map of discriminator values to class names
     * @param bool                             $passDiscriminator Whether to pass discriminator to constructor payload (default: true)
     * @param null|class-string                $value             Optional fallback concrete type for non-discriminated nested object
     */
    public function __construct(
        public readonly ?string $discriminator = null,
        public readonly ?array $mapping = null,
        public readonly bool $passDiscriminator = true,
        public readonly ?string $value = null,
    ) {}
}
