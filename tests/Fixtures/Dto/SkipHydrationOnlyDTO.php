<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Skip;

class SkipHydrationOnlyDTO
{
    public function __construct(
        #[Skip(hydration: true, dehydration: false)]
        public readonly ?string $internal = null,
        public readonly string $visible = 'ok',
    ) {}
}
