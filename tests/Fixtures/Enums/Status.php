<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Enums;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
