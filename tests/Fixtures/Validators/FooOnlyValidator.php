<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Validators\Validator;

class FooOnlyValidator implements Validator
{
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError
    {
        if (!$value instanceof ValidationCustomPayload || $value->value !== 'foo') {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: 'Custom validator failed: expected payload value "foo"',
                key: $key,
                fullPath: $fullPath,
            );
        }

        return null;
    }
}
