<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Email;

readonly class ValidationMixedPropertyEmailDTO
{
    public function __construct(
        #[Email]
        public mixed $contact,
    ) {}
}
