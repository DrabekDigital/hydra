<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(new UnderscoreCase())]
class NamingStrategyClaimDTO
{
    public function __construct(
        public readonly string $firstName,
    ) {}
}
