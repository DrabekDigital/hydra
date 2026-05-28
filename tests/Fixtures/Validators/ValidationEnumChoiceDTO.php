<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

readonly class ValidationEnumChoiceDTO
{
    public function __construct(
        #[Choice([Status::Draft, Status::Published])]
        public Status $status,
    ) {}
}
