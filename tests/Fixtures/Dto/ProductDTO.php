<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Tests\Fixtures\Mappers\AsMoney;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\Money;

class ProductDTO
{
    public function __construct(
        public readonly string $name,
        #[AsMoney]
        public readonly Money $price,
    ) {}
}
