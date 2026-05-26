<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same shape as StructureHashWithDefaultDTO, without a parameter default on the second property.
 */
final class StructureHashNoDefaultDTO
{
    public function __construct(
        public readonly string $first,
        public readonly string $second,
    ) {}
}
