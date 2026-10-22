<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions\Shipping;

use DrabekDigital\Hydra\Mappers\Mapper;

class LabelMapper implements Mapper
{
    private const PREFIX = 'shipping:';

    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Expected string at {$fullPath}");
        }

        return self::PREFIX.$value;
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        if (!is_string($value) || !str_starts_with($value, self::PREFIX)) {
            throw new \InvalidArgumentException("Expected prefixed label at {$fullPath}");
        }

        return substr($value, strlen(self::PREFIX));
    }
}
