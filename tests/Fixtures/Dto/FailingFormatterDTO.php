<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomFormatter;

class FailingFormatterDTO
{
    public function __construct(
        #[CustomFormatter(
            dehydrate: static function (string $value): string {
                throw new \RuntimeException('Formatter dehydration intentionally failed');
            },
        )]
        public readonly string $value,
        public readonly string $other,
    ) {}
}
