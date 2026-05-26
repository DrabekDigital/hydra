<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Skip;

class SkipClaimDTO
{
    public function __construct(
        #[Skip]
        public readonly ?string $name = null,
        public readonly string $visible = 'ok',
    ) {}
}
