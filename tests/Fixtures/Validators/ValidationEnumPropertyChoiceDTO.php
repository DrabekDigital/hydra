<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

readonly class ValidationEnumPropertyChoiceDTO
{
    public function __construct(
        #[Choice(enumClass: Status::class)]
        public Status $status,
    ) {}
}
