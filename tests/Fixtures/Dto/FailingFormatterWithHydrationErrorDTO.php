<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\Errors\HydrationError;
use DrabekDigital\Hydra\Errors\HydrationErrorCode;
use DrabekDigital\Hydra\Exceptions\FormatterException;

class FailingFormatterWithHydrationErrorDTO
{
    public function __construct(
        #[CustomFormatter(
            hydrate: static function (string $value): string {
                throw FormatterException::withHydrationError(new HydrationError(
                    code: HydrationErrorCode::INVALID_VALUE,
                    programmerMessage: 'Formatter hydration mismatch for value',
                    key: 'value',
                    fullPath: 'value',
                ));
            },
        )]
        public readonly string $value,
        public readonly string $other,
    ) {}
}
