<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class DefaultValueDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $status = 'active',
        public readonly int $count = 0,
    ) {}
}
