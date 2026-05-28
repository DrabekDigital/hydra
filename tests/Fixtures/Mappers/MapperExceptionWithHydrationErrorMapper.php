<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

use DrabekDigital\Hydra\Errors\HydrationError;
use DrabekDigital\Hydra\Errors\HydrationErrorCode;
use DrabekDigital\Hydra\Exceptions\MapperException;
use DrabekDigital\Hydra\Mappers\Mapper;

class MapperExceptionWithHydrationErrorMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        throw MapperException::withHydrationError(new HydrationError(
            code: HydrationErrorCode::INVALID_VALUE,
            programmerMessage: "Custom mapper hydration mismatch for '{$key}'",
            key: $key,
            fullPath: $fullPath,
        ));
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return $value;
    }
}
