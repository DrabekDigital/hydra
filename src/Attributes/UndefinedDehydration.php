<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

enum UndefinedDehydration
{
    case SKIP;
    case AS_NULL;
    case AS_VALUE;
}
