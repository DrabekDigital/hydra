<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\Undefinable;

class MixedDefaultsDTO
{
    public function __construct(
        public readonly string $required,
        #[DefaultValue('attr_default')]
        public readonly string $attrDefault,
        #[Undefinable]
        public readonly ?string $undefinable,
        public readonly string $paramDefault = 'param_default',
    ) {}
}
