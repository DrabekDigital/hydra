<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

abstract class RecursiveTreeItemDTO
{
    public function __construct(
        public readonly string $name,
    ) {}
}
