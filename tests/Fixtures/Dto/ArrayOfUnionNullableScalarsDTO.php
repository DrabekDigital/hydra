<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;

readonly class ArrayOfUnionNullableScalarsDTO
{
    /**
     * @param array<int, int|string|null> $items
     */
    public function __construct(
        #[ArrayOf('string|int|null')]
        public array $items,
    ) {}
}
