<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same properties as StructureHashOrderFirstDTO, different constructor parameter order.
 */
final class StructureHashOrderSecondDTO
{
    public function __construct(
        public readonly int $beta,
        public readonly string $alpha,
    ) {}
}
