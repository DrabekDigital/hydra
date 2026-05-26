<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

use DrabekDigital\Hydra\Mappers\Mapper;

class MoneyMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException("Expected int cents at {$fullPath}");
        }

        return new Money($value);
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        if (!$value instanceof Money) {
            throw new \InvalidArgumentException("Expected Money instance at {$fullPath}");
        }

        return $value->cents;
    }
}
