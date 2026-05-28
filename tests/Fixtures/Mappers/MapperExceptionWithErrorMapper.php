<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

use DrabekDigital\Hydra\Errors\DehydrationError;
use DrabekDigital\Hydra\Errors\DehydrationErrorCode;
use DrabekDigital\Hydra\Exceptions\MapperException;
use DrabekDigital\Hydra\Mappers\Mapper;

class MapperExceptionWithErrorMapper implements Mapper
{
    public function hydrate(mixed $value, string $key, string $fullPath): mixed
    {
        return $value;
    }

    public function dehydrate(mixed $value, string $key, string $fullPath): mixed
    {
        throw MapperException::withDehydrationError(new DehydrationError(
            code: DehydrationErrorCode::TYPE_MISMATCH,
            programmerMessage: "Custom mapper mismatch for '{$key}'",
            key: $key,
            fullPath: $fullPath,
        ));
    }
}
