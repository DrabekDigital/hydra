<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

class EnumWithDefaultDTO
{
    public function __construct(
        public readonly string $name,
        #[DefaultValue(Status::Draft)]
        public readonly Status $status,
    ) {}
}
