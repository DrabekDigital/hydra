<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same properties as StructureHashOrderSecondDTO, different constructor parameter order.
 */
final class StructureHashOrderFirstDTO
{
    public function __construct(
        public readonly string $alpha,
        public readonly int $beta,
    ) {}
}
