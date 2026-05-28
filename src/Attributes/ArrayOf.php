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
     * @param string $type The item type:
     *                     - scalar ('string', 'int', 'float', 'bool', 'null', 'mixed')
     *                     - scalar union without spaces (e.g. 'string|int')
     *                     - object class name
     */
    public function __construct(
        public readonly string $type = 'mixed',
    ) {}
}
