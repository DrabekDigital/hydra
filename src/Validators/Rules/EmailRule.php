<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Email;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class EmailRule
{
    public function __construct(private readonly Email $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_EMAIL,
                programmerMessage: $this->rule->message ?? "String '{$key}' is not a valid email",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
