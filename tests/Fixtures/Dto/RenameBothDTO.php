<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Rename;

class RenameBothDTO
{
    public function __construct(
        #[Rename(from: 'input_key', to: 'output_key')]
        public readonly string $firstName,
    ) {}
}
