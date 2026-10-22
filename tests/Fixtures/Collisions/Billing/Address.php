<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing;

use DrabekDigital\Hydra\Tests\Fixtures\Collisions\AddressInterface;

readonly class Address implements AddressInterface
{
    public function __construct(
        public string $street,
        public string $vatId,
    ) {}
}
