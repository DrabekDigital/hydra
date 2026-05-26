<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

readonly class ValidationCustomPayload
{
    public function __construct(
        public string $value,
    ) {}
}
