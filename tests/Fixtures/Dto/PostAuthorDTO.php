<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\NamingStrategies\UnderscoreCase;

#[NamingStrategy(new UnderscoreCase())]
class PostAuthorDTO
{
    public string $displayName;
    public ?string $bio = null;
    public string $role = 'author';
}
