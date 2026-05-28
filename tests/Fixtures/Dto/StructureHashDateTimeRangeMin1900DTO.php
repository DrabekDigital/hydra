<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;

final class StructureHashDateTimeRangeMin1900DTO
{
    public function __construct(
        #[DateTimeRange(min: new \DateTimeImmutable('1900-01-01T00:00:00+00:00'))]
        public readonly \DateTimeImmutable $publishedAt,
    ) {}
}
