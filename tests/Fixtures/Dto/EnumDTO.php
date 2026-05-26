<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;

class EnumDTO
{
    public function __construct(
        public readonly Status $status,
        public readonly Priority $priority,
    ) {}
}
