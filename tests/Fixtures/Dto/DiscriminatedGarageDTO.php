<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;

readonly class DiscriminatedGarageDTO
{
    /**
     * @param list<Vehicle> $vehicles
     */
    public function __construct(
        #[ObjectType(
            discriminator: 'type',
            mapping: [
                'car' => Car::class,
                'motorcycle' => Motorcycle::class,
            ],
            passDiscriminator: false
        )]
        public Vehicle $featured,
        #[ListOf(
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
