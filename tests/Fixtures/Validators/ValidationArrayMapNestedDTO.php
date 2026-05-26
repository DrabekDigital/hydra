<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\MapOf;

readonly class ValidationArrayMapNestedDTO
{
    /**
     * @param list<ValidationChildDTO>          $arrayChildren
     * @param array<string, ValidationChildDTO> $mapChildren
     */
    public function __construct(
        #[ArrayOf(type: ValidationChildDTO::class)]
        public array $arrayChildren,
        #[MapOf(key: 'string', value: ValidationChildDTO::class)]
        public array $mapChildren,
    ) {}
}
