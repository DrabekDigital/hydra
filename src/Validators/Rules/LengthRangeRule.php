<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class LengthRangeRule
{
    public function __construct(private readonly LengthRange $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        $errors = [];
        $length = mb_strlen($value);

        if ($this->rule->min !== null && $length < $this->rule->min) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::TOO_SHORT,
                programmerMessage: "String '{$key}' is too short, minimum length is {$this->rule->min}",
                key: $key,
                fullPath: $fullPath,
            );
        }

        if ($this->rule->max !== null && $length > $this->rule->max) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::TOO_LONG,
                programmerMessage: "String '{$key}' is too long, maximum length is {$this->rule->max}",
                key: $key,
                fullPath: $fullPath,
            );
        }

        return $errors;
    }
}
