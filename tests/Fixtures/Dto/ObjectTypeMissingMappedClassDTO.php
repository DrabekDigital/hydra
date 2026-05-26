<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Vehicle;

final class ObjectTypeMissingMappedClassDTO
{
    public function __construct(
        #[ObjectType(
            discriminator: 'type',
            mapping: [
                'car' => Car::class,
                'ghost' => \Stringable::class,
            ],
            passDiscriminator: false
        )]
        public readonly Vehicle $featured,
    ) {}
}
