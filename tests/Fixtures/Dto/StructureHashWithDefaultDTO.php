<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same shape as StructureHashNoDefaultDTO, but the second parameter has a default value.
 */
final class StructureHashWithDefaultDTO
{
    public function __construct(
        public readonly string $first,
        public readonly string $second = 'default',
    ) {}
}
