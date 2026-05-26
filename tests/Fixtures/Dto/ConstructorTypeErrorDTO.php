<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

final class ConstructorTypeErrorDTO
{
    public function __construct(
        public readonly string $name,
    ) {
        throw new \TypeError('Simulated constructor type error');
    }
}
