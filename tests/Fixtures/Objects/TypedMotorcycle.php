<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Objects;

class TypedMotorcycle extends TypedVehicle
{
    public function __construct(
        string $type,
        string $brand,
        int $year,
        public readonly int $engineCC,
    ) {
        parent::__construct($type, $brand, $year);
    }
}
