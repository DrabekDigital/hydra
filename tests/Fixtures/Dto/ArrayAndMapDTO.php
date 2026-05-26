<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\MapOf;

readonly class ArrayAndMapDTO
{
    /**
     * @param list<string>       $tags
     * @param array<string, int> $scores
     */
    public function __construct(
        #[ArrayOf('string')]
        public array $tags,
        #[MapOf(key: 'string', value: 'int')]
        public array $scores,
    ) {}
}
