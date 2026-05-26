<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

class EnumUndefinableDTO
{
    public function __construct(
        public readonly string $name,
        #[Undefinable]
        public readonly ?Status $status,
    ) {}
}
