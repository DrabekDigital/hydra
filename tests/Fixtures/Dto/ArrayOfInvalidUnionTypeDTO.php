<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;

readonly class ArrayOfInvalidUnionTypeDTO
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        #[ArrayOf('string|int|array')]
        public array $items,
    ) {}
}
