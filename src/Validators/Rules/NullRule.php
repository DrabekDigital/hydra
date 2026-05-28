<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\IsNull;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class NullRule
{
    public function __construct(private readonly IsNull $rule) {}

    /** @return list<ValidationError> */
    public function validate(mixed $value, string $key, string $fullPath): array
    {
        if ($value !== null) {
            return [new ValidationError(
                code: ValidationErrorCode::MUST_BE_NULL,
                programmerMessage: $this->rule->message ?? "Value '{$key}' must be null",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
