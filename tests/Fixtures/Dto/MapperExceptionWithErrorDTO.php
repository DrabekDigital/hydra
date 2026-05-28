<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomMapper;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\MapperExceptionWithErrorMapper;

class MapperExceptionWithErrorDTO
{
    public function __construct(
        #[CustomMapper(mapper: MapperExceptionWithErrorMapper::class)]
        public readonly mixed $value,
        public readonly string $other,
    ) {}
}
