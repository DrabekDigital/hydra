<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Mappers;

class Money
{
    public function __construct(
        public readonly int $cents,
    ) {}
}
