<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\MapOf;

final class RecursiveMapNodeDTO
{
    /**
     * @param array<string, RecursiveMapNodeDTO> $children
     */
    public function __construct(
        public readonly string $name,
        #[MapOf(value: RecursiveMapNodeDTO::class)]
        public readonly array $children = [],
    ) {}
}
