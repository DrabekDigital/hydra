<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property as a list (indexed array) of a specific type.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ListOf
{
    /**
     * @param class-string|string              $value             The item type (e.g., Person::class or 'string')
     * @param null|string                      $discriminator     Field name in items to use for polymorphic dispatch
     * @param null|array<string, class-string> $mapping           Map of discriminator values to class names
     * @param bool                             $passDiscriminator Whether to pass the discriminator field to constructor (default: true)
     */
    public function __construct(
        public readonly string $value,
        public readonly ?string $discriminator = null,
        public readonly ?array $mapping = null,
        public readonly bool $passDiscriminator = true,
    ) {}
}
