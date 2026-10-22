<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping;

use DrabekDigital\Hydra\Attributes\MapperAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class AsLabel implements MapperAttribute
{
    public function getMapperClass(): string
    {
        return LabelMapper::class;
    }
}
