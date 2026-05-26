<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Validators\Validator;

class BarOnlyValidator implements Validator
{
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError
    {
        if (!$value instanceof ValidationCustomPayload || $value->value !== 'bar') {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: 'Custom validator failed: expected payload value "bar"',
                key: $key,
                fullPath: $fullPath,
            );
        }

        return null;
    }
}
