<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\Skip;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(new UnderscoreCase())]
class BlogPostDTO
{
    /** Required — input key: title */
    public string $title;

    /** Optional — input key: subtitle */
    public ?string $subtitle = null;

    /** Has PHP default — input key: status */
    public string $status = 'draft';

    /** Has PHP default — input key: view_count (NamingStrategy) */
    public int $viewCount = 0;

    /**
     * Hydrated from input but never included in dehydrated output.
     * Input key: internal_id (NamingStrategy).
     */
    #[Skip(hydration: false, dehydration: true)]
    public ?string $internalId = null;

    /**
     * Input key overridden to created_by; output key follows NamingStrategy → author.
     */
    #[Rename(from: 'created_by')]
    public PostAuthorDTO $author;

    /**
     * List of no-constructor nested objects — input key: tags.
     *
     * @var list<PostTagDTO>
     */
    #[ListOf(PostTagDTO::class)]
    public array $tags = [];

    /**
     * Indexed array of scalars — input key: categories.
     *
     * @var list<string>
     */
    #[ArrayOf('string')]
    public array $categories = [];
}
