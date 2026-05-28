<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;

class UndefinableWithFallbackDTO
{
    public function __construct(
        public readonly string $name,
        #[Undefinable(onUndefinedDehydration: UndefinedDehydration::AS_VALUE, undefinedAs: 'missing')]
        public readonly ?string $optional,
    ) {}
}
