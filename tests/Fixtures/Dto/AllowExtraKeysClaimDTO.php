<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\AllowExtraKeys;

#[AllowExtraKeys]
class AllowExtraKeysClaimDTO
{
    public function __construct(
        public readonly string $name,
    ) {}
}
