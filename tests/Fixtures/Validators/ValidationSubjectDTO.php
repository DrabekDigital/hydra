<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\CustomValidator;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Attributes\Validation\URL;

readonly class ValidationSubjectDTO
{
    /**
     * @param list<string>             $tags
     * @param list<ValidationChildDTO> $children
     */
    public function __construct(
        #[NumberRange(min: 18, max: 99)]
        public int $age,
        #[LengthRange(min: 2, max: 5)]
        public string $nickname,
        #[NonEmptyString]
        public string $title,
        #[StringMatches('/^[A-Z]+$/')]
        public string $code,
        #[URL(allowedSchemes: ['https'])]
        public string $website,
        #[Count(min: 1, max: 2)]
        #[UniqueItems]
        public array $tags,
        #[CustomValidator(FooOnlyValidator::class)]
        public ValidationCustomPayload $payload,
        #[DateTimeRange(min: new \DateTimeImmutable('1900-01-01'), max: new \DateTimeImmutable('2026-12-31'), bounds: '[]')]
        public \DateTimeInterface $birthday,
        public ValidationChildDTO $profile,
        #[ListOf(ValidationChildDTO::class)]
        public array $children,
    ) {}
}
