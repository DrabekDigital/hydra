<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Rename;

class RenameClaimDTO
{
    public function __construct(
        #[Rename(from: 'first_name', to: 'first_name')]
        public readonly string $firstName,
    ) {}
}
