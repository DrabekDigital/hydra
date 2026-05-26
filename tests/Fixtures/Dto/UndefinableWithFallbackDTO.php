<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;

class UndefinableWithFallbackDTO
{
    public function __construct(
        public readonly string $name,
        #[Undefinable(onDehydrationWhen: 'missing')]
        public readonly ?string $optional,
    ) {}
}
