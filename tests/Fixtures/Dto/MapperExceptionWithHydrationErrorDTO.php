<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomMapper;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\MapperExceptionWithHydrationErrorMapper;

class MapperExceptionWithHydrationErrorDTO
{
    public function __construct(
        #[CustomMapper(mapper: MapperExceptionWithHydrationErrorMapper::class)]
        public readonly mixed $value,
        public readonly string $other,
    ) {}
}
