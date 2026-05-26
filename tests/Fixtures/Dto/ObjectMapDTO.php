<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\MapOf;

readonly class ObjectMapDTO
{
    /**
     * @param array<string, AddressDTO> $addresses
     */
    public function __construct(
        #[MapOf(key: 'string', value: AddressDTO::class)]
        public array $addresses,
    ) {}
}
