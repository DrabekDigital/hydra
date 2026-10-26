<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Attributes\Validation\NotNull;

class ValidatorUninitializedPublicPropertyDTO
{
    #[LengthRange(min: 3)]
    public string $title;

    #[NotNull]
    public ?string $note;

    public ?ValidatorUndefinableChildDTO $child;

    #[Undefinable]
    #[NotNull]
    public ?string $comment;
}
