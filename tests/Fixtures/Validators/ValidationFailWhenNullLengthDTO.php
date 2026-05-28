<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NullValidationBehavior;

readonly class ValidationFailWhenNullLengthDTO
{
    public function __construct(
        #[LengthRange(min: 1, onNull: NullValidationBehavior::FailWhenNull)]
        public ?string $nickname,
    ) {}
}
