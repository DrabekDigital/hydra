<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Objects;

abstract class TypedVehicle
{
    public function __construct(
        public readonly string $type,
        public readonly string $brand,
        public readonly int $year,
    ) {}
}
