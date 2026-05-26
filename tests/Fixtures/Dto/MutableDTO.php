<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class MutableDTO
{
    public function __construct(
        public string $name,
        public int $count,
    ) {}
}
