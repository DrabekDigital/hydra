<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Skip;

class SkipHydrationPublicPropertyDTO
{
    public string $visible;

    #[Skip(hydration: true, dehydration: false)]
    public string $internal;
}
