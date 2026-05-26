<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Rename;

class RenameFromOnlyDTO
{
    public function __construct(
        #[Rename(from: 'api_name')]
        public readonly string $firstName,
    ) {}
}
