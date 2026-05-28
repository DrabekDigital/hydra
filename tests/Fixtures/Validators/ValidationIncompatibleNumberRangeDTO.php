<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;

readonly class ValidationIncompatibleNumberRangeDTO
{
    public function __construct(
        #[NumberRange(min: 1, max: 10)]
        public string $quantity,
    ) {}
}
