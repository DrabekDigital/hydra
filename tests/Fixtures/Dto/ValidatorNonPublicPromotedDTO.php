<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;

class ValidatorNonPublicPromotedDTO
{
    public function __construct(
        #[LengthRange(min: 3)]
        private readonly string $name,
        protected readonly ValidatorUndefinableChildDTO $child,
        #[Undefinable]
        #[LengthRange(min: 3)]
        private readonly ?string $alias,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getChild(): ValidatorUndefinableChildDTO
    {
        return $this->child;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }
}
