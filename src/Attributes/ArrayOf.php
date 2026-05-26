<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

/**
 * Marks a property as an array of scalar type.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    /**
     * @param string $type The item type ('string', 'int', 'float', 'bool', 'mixed')
     */
    public function __construct(
        public readonly string $type = 'mixed',
    ) {}
}
