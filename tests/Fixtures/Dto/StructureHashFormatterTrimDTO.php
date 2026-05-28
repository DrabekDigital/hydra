<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomFormatter;

final class StructureHashFormatterTrimDTO
{
    public function __construct(
        #[CustomFormatter(
            hydrate: static function (string $value): string {
                return trim($value);
            },
        )]
        public readonly string $name,
    ) {}
}
