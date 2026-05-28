<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

readonly class IdentifiableObject
{
    public function __construct(
        public int $id,
    ) {}
}
