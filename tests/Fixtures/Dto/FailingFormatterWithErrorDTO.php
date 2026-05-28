<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\Errors\DehydrationError;
use DrabekDigital\Hydra\Errors\DehydrationErrorCode;
use DrabekDigital\Hydra\Exceptions\FormatterException;

class FailingFormatterWithErrorDTO
{
    public function __construct(
        #[CustomFormatter(
            dehydrate: static function (string $value): string {
                throw FormatterException::withDehydrationError(new DehydrationError(
                    code: DehydrationErrorCode::TYPE_MISMATCH,
                    programmerMessage: 'Formatter mismatch for value',
                    key: 'value',
                    fullPath: 'value',
                ));
            },
        )]
        public readonly string $value,
        public readonly string $other,
    ) {}
}
