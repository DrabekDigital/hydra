<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Objects;

class Motorcycle extends Vehicle
{
    public function __construct(
        string $brand,
        int $year,
        public readonly int $engineCC,
    ) {
        parent::__construct($brand, $year);
    }
}
