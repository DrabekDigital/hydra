<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Validation\LengthRange;

class ValidatorUndefinableChildDTO
{
    public function __construct(
        #[LengthRange(min: 3)]
        public readonly string $name,
    ) {}
}
