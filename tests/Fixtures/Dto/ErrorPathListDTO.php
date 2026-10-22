<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

readonly class ErrorPathListDTO
{
    /**
     * @param list<SimpleDTO> $items
     */
    public function __construct(
        #[ListOf(SimpleDTO::class)]
        public array $items,
    ) {}
}
