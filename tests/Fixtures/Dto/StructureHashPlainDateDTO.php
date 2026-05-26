<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Same shape as DateTimeDTO, but without a DateTime attribute on the date property.
 */
final class StructureHashPlainDateDTO
{
    public function __construct(
        public readonly string $name,
        public readonly \DateTimeImmutable $date,
    ) {}
}
