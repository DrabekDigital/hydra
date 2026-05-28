<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;

readonly class ArrayOfNullOnlyDTO
{
    /**
     * @param array<int, null> $items
     */
    public function __construct(
        #[ArrayOf('null')]
        public array $items,
    ) {}
}
