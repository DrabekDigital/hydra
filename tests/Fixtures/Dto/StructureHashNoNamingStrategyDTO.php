<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same shape as NamingStrategyClaimDTO, without a class-level NamingStrategy attribute.
 */
final class StructureHashNoNamingStrategyDTO
{
    public function __construct(
        public readonly string $firstName,
    ) {}
}
