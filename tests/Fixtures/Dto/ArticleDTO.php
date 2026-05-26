<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

class ArticleDTO
{
    /**
     * @param list<TagDTO> $tags
     */
    public function __construct(
        public readonly string $title,
        #[ListOf(TagDTO::class)]
        public readonly array $tags,
    ) {}
}
