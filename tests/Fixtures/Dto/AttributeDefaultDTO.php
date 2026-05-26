<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DefaultValue;

class AttributeDefaultDTO
{
    public function __construct(
        public readonly string $name,
        #[DefaultValue('pending')]
        public readonly string $status,
    ) {}
}
