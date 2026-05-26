<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;

readonly class ValidationNumberRangeBoundsDTO
{
    public function __construct(
        #[NumberRange(min: 0.5, max: 1.5, bounds: '[]')]
        public float $inclusive,
        #[NumberRange(min: 0.5, max: 1.5, bounds: '()')]
        public float $exclusive,
        #[NumberRange(min: 0.5, max: 1.5, bounds: '[)')]
        public float $rightExclusive,
        #[NumberRange(min: 0.5, max: 1.5, bounds: '(]')]
        public float $leftExclusive,
    ) {}
}
