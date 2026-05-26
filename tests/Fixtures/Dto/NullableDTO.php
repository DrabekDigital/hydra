<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class NullableDTO
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?int $age,
    ) {}
}
