<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

class ValidationCyclicNodeDTO
{
    public function __construct(
        public string $name,
        public ?self $next = null,
    ) {}
}
