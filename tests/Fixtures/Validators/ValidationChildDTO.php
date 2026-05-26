<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\LengthRange;

readonly class ValidationChildDTO
{
    public function __construct(
        #[LengthRange(min: 3)]
        public string $name,
    ) {}
}
