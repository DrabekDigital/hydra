<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

final class RecursiveBrokenNodeDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?RecursiveBrokenNodeDTO $next = null,
        public readonly ?ListOfMissingMappedClassDTO $broken = null,
    ) {}
}
