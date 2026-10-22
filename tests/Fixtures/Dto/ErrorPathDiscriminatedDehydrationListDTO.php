<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

readonly class ErrorPathDiscriminatedDehydrationListDTO
{
    /**
     * @param list<NoConstructorDTO> $items
     */
    public function __construct(
        #[ListOf(
            value: NoConstructorDTO::class,
            discriminator: 'kind',
            mapping: ['plain' => NoConstructorDTO::class],
        )]
        public array $items,
    ) {}
}
