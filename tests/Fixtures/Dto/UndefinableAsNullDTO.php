<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;

class UndefinableAsNullDTO
{
    public function __construct(
        public readonly string $name,
        #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_NULL)]
        public readonly ?string $optional,
    ) {}
}
