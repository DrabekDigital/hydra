<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class AllScalarsDTO
{
    public function __construct(
        public readonly string $string,
        public readonly int $int,
        public readonly float $float,
        public readonly bool $bool,
    ) {}
}
