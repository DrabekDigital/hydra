<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class UnionScalarDTO
{
    public function __construct(
        public readonly float|int $number,
        public readonly int|string $identifier,
    ) {}
}
