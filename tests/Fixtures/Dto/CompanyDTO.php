<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

class CompanyDTO
{
    public function __construct(
        public readonly string $name,
        public readonly AddressDTO $headquarters,
        public readonly ?AddressDTO $mailingAddress,
    ) {}
}
