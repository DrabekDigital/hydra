<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

use DrabekDigital\Hydra\Exceptions\MapperException;
use DrabekDigital\Hydra\Mappers\Mapper;

class MapperExceptionMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return $value;
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        throw new MapperException("MapperException dehydration intentionally failed for '{$key}'");
    }
}
