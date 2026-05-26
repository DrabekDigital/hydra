<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomFormatter;

class WorkingFormatterDTO
{
    public function __construct(
        #[CustomFormatter(
            hydrate: static function (string $value): string {
                return strtoupper($value);
            },
            dehydrate: static function (string $value): string { return strtolower($value); },
        )]
        public readonly string $both,
        #[CustomFormatter(
            hydrate: static function (string $value): string { return strtoupper($value); },
        )]
        public readonly string $hydrateOnly,
        #[CustomFormatter(
            dehydrate: static function (string $value): string { return strtolower($value); },
        )]
        public readonly string $dehydrateOnly,
    ) {}
}
