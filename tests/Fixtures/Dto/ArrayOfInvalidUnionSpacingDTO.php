<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;

readonly class ArrayOfInvalidUnionSpacingDTO
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        #[ArrayOf('string |int')]
        public array $items,
    ) {}
}
