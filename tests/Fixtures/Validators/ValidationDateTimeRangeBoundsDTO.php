<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;

readonly class ValidationDateTimeRangeBoundsDTO
{
    public function __construct(
        #[DateTimeRange(
            min: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            max: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
            bounds: '[]'
        )]
        public \DateTimeInterface $inclusive,
        #[DateTimeRange(
            min: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            max: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
            bounds: '()'
        )]
        public \DateTimeInterface $exclusive,
        #[DateTimeRange(
            min: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            max: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
            bounds: '[)'
        )]
        public \DateTimeInterface $rightExclusive,
        #[DateTimeRange(
            min: new \DateTimeImmutable('2024-01-01T00:00:00+00:00'),
            max: new \DateTimeImmutable('2024-01-31T00:00:00+00:00'),
            bounds: '(]'
        )]
        public \DateTimeInterface $leftExclusive,
    ) {}
}
