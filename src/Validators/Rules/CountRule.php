<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class CountRule
{
    public function __construct(private readonly Count $rule) {}

    /**
     * @param array<mixed> $value
     *
     * @return list<ValidationError>
     */
    public function validate(array $value, string $key, string $fullPath): array
    {
        $errors = [];
        $count = count($value);

        if ($this->rule->min !== null && $count < $this->rule->min) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::COUNT_TOO_LOW,
                programmerMessage: "Array '{$key}' has too few items, minimum is {$this->rule->min}",
                key: $key,
                fullPath: $fullPath,
            );
        }

        if ($this->rule->max !== null && $count > $this->rule->max) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::COUNT_TOO_HIGH,
                programmerMessage: "Array '{$key}' has too many items, maximum is {$this->rule->max}",
                key: $key,
                fullPath: $fullPath,
            );
        }

        return $errors;
    }
}
