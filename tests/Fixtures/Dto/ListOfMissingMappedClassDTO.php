<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;

final class ListOfMissingMappedClassDTO
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
                'ghost' => \Stringable::class,
            ],
            passDiscriminator: false
        )]
        public readonly array $vehicles,
    ) {}
}
