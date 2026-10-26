<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NonEmptyString;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;

class ValidatorUndefinableDTO
{
    public function __construct(
        #[LengthRange(min: 3)]
        public readonly string $name,
        #[Undefinable]
        public readonly ?string $plain,
        #[Undefinable]
        #[NonEmptyString]
        #[LengthRange(min: 3, max: 10)]
        public readonly ?string $nickname,
        #[Undefinable]
        #[NotNull]
        public readonly ?string $code,
    ) {}
}
