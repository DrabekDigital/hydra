<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\StringMatches;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class StringMatchesRule
{
    public function __construct(private readonly StringMatches $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (@preg_match($this->rule->pattern, $value) !== 1) {
            return [new ValidationError(
                code: ValidationErrorCode::PATTERN_MISMATCH,
                programmerMessage: $this->rule->message ?? "String '{$key}' does not match required pattern",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
