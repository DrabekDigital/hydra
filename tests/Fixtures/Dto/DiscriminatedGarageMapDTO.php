<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;

readonly class DiscriminatedGarageMapDTO
{
    /**
     * @param array<string, Vehicle> $vehicles
     */
    public function __construct(
        #[MapOf(
            key: 'string',
            value: Vehicle::class,
            discriminator: 'type',
            mapping: [
                'car' => Car::class,
                'motorcycle' => Motorcycle::class,
            ],
            passDiscriminator: false
        )]
        public array $vehicles,
    ) {}
}
