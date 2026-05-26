<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Renames a property for hydration/dehydration.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Rename
{
    /**
     * @param null|string $from Name to use when reading from input (hydration)
     * @param null|string $to   Name to use when writing to output (dehydration)
     */
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
        if ($from === null && $to === null) {
            throw new \InvalidArgumentException('At least one of "from" or "to" must be specified');
        }
    }
}
