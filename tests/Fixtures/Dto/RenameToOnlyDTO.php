<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Rename;

class RenameToOnlyDTO
{
    public function __construct(
        #[Rename(to: 'output_name')]
        public readonly string $firstName,
    ) {}
}
