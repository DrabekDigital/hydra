<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

class ScalarListDTO
{
    /**
     * @param list<string> $strings
     * @param list<int>    $ints
     */
    public function __construct(
        #[ListOf('string')]
        public readonly array $strings,
        #[ListOf('int')]
        public readonly array $ints,
    ) {}
}
