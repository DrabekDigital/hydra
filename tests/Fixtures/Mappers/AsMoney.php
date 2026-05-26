<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

use DrabekDigital\Hydra\Attributes\MapperAttribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
class AsMoney implements MapperAttribute
{
    public function getMapperClass(): string
    {
        return MoneyMapper::class;
    }
}
