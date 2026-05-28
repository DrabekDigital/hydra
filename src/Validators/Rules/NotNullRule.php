<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\NotNull;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class NotNullRule
{
    public function __construct(private readonly NotNull $rule) {}

    /** @return list<ValidationError> */
    public function validate(mixed $value, string $key, string $fullPath): array
    {
        if ($value === null) {
            return [new ValidationError(
                code: ValidationErrorCode::NOT_NULL,
                programmerMessage: $this->rule->message ?? "Value '{$key}' must not be null",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
