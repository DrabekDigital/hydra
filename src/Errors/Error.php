<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Errors;

abstract class Error
{
    public function __construct(
        public \BackedEnum $code,
        public string $programmerMessage,
        public bool|int|string|null $key,
        public string $fullPath,
    ) {}
}
