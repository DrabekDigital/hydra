<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ObjectType;

final class ObjectTypeValueDTO
{
    public function __construct(
        #[ObjectType(value: AddressDTO::class)]
        public readonly AddressDTO $address,
    ) {}
}
