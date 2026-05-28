<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

readonly class ValidationConstraintAttributeDTO
{
    public function __construct(
        #[RequiresFooPayload]
        public ValidationCustomPayload $payload,
    ) {}
}
