<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\Validation\Count;

class ValidatorUndefinableNestedDTO
{
    /**
     * @param ?list<ValidatorUndefinableChildDTO> $children
     */
    public function __construct(
        public readonly string $name,
        #[Undefinable]
        public readonly ?ValidatorUndefinableChildDTO $child,
        #[Undefinable]
        #[ListOf(ValidatorUndefinableChildDTO::class)]
        #[Count(min: 1)]
        public readonly ?array $children,
    ) {}
}
