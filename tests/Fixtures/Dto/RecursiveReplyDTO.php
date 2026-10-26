<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;

final class RecursiveReplyDTO
{
    /**
     * @param list<RecursiveCommentDTO> $comments
     */
    public function __construct(
        public readonly string $text,
        public readonly ?RecursiveCommentDTO $quoted = null,
        #[ListOf(RecursiveCommentDTO::class)]
        public readonly array $comments = [],
    ) {}
}
