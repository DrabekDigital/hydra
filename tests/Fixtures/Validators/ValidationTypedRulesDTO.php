<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

readonly class ValidationTypedRulesDTO
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[LengthRange(min: 2, max: 20)]
        public string $name,
        #[NumberRange(min: 0, max: 150)]
        public int $age,
        #[NumberRange(min: 0.0, max: 999.99)]
        public float $weight,
        #[NonEmptyString]
        public string $code,
        #[Count(min: 1, max: 5)]
        #[UniqueItems]
        public array $tags,
        #[DateTimeRange(min: new \DateTimeImmutable('2000-01-01'), max: new \DateTimeImmutable('2026-12-31'))]
        public \DateTimeImmutable $birthday,
        #[Choice(enumClass: Status::class)]
        public Status $status,
    ) {}
}
