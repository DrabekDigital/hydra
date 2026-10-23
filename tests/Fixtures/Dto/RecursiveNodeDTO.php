<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;

final class RecursiveNodeDTO
{
    /**
     * @param list<RecursiveNodeDTO> $children
     */
    public function __construct(
        #[NonEmptyString]
        public readonly string $name,
        #[ListOf(RecursiveNodeDTO::class)]
        public readonly array $children = [],
    ) {}
}
