<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\Undefinable;

/**
 * DTO with public properties, no promotion, not readonly.
 */
class PublicPropertiesDTO
{
    public string $required;
    public ?string $nullable;
    public string $withDefault;
    public ?string $undefinable;

    public function __construct(
        string $required,
        ?string $nullable,
        #[DefaultValue('default_value')]
        string $withDefault,
        #[Undefinable]
        ?string $undefinable,
    ) {
        $this->required = $required;
        $this->nullable = $nullable;
        $this->withDefault = $withDefault;
        $this->undefinable = $undefinable;
    }
}
