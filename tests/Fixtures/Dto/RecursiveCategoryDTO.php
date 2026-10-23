<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

final class RecursiveCategoryDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?RecursiveCategoryDTO $parent = null,
    ) {}
}
