<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Skip;

class SkipDehydrationOnlyDTO
{
    public function __construct(
        #[Skip(hydration: false, dehydration: true)]
        public readonly string $secret,
        public readonly string $visible,
    ) {}
}
