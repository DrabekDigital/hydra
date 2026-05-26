<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\TypedCar;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\TypedMotorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\TypedVehicle;

readonly class DiscriminatedGaragePassThroughSuccessDTO
{
    /**
     * @param list<TypedVehicle> $vehicles
     */
    public function __construct(
        #[ListOf(
            value: TypedVehicle::class,
            discriminator: 'type',
            mapping: [
                'car' => TypedCar::class,
                'motorcycle' => TypedMotorcycle::class,
            ],
            passDiscriminator: true
        )]
        public array $vehicles,
    ) {}
}
