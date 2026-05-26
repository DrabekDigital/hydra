<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DateTime;

class DateTimeDTO
{
    public function __construct(
        public readonly string $name,
        #[DateTime(format: 'Y-m-d')]
        public readonly \DateTimeImmutable $date,
    ) {}
}
