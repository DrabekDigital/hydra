<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class NonEmptyStringRule
{
    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if ($value === '') {
            return [new ValidationError(
                code: ValidationErrorCode::TOO_SHORT,
                programmerMessage: "String '{$key}' cannot be empty",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
