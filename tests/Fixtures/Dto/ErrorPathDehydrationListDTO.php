<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

readonly class ErrorPathDehydrationListDTO
{
    /**
     * @param list<NoConstructorDTO> $items
     */
    public function __construct(
        #[ListOf(NoConstructorDTO::class)]
        public array $items,
    ) {}
}
