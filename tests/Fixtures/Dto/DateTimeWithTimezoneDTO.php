<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DateTime;

class DateTimeWithTimezoneDTO
{
    public function __construct(
        #[DateTime(format: 'Y-m-d H:i:s', timezone: 'UTC')]
        public readonly \DateTimeImmutable $timestamp,
    ) {}
}
