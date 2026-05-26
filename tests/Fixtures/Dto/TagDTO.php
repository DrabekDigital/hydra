<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class TagDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
    ) {}
}
