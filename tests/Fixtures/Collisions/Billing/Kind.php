<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions\Billing;

enum Kind: string
{
    case Invoice = 'invoice';
    case Receipt = 'receipt';
}
