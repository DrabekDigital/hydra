<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions;

readonly class DehydrationError
{
    public function __construct(
        public string $reason,
    ) {}
}
