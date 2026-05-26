<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;

readonly class DiscriminatedGaragePassThroughDTO
{
    /**
     * @param list<Vehicle> $vehicles
     */
    public function __construct(
        #[ListOf(
            value: Vehicle::class,
            discriminator: 'type',
            mapping: [
                'car' => Car::class,
                'motorcycle' => Motorcycle::class,
            ],
            passDiscriminator: true
        )]
        public array $vehicles,
    ) {}
}
