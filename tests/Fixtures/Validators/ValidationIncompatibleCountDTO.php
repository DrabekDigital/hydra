<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Count;

readonly class ValidationIncompatibleCountDTO
{
    public function __construct(
        #[Count(min: 1)]
        public string $label,
    ) {}
}
