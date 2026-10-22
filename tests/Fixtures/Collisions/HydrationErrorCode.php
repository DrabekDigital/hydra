<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions;

enum HydrationErrorCode: string
{
    case Missing = 'missing';
    case Invalid = 'invalid';
}
