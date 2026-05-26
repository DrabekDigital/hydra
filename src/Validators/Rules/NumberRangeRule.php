<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class NumberRangeRule
{
    public function __construct(private readonly NumberRange $rule) {}

    /** @return list<ValidationError> */
    public function validate(float|int $value, string $key, string $fullPath): array
    {
        $errors = [];

        if ($this->rule->min !== null) {
            $tooLow = $this->rule->isMinInclusive() ? $value < $this->rule->min : $value <= $this->rule->min;
            if ($tooLow) {
                $operator = $this->rule->isMinInclusive() ? '>=' : '>';
                $errors[] = new ValidationError(
                    code: ValidationErrorCode::OUT_OF_RANGE,
                    programmerMessage: "Value of '{$key}' must be {$operator} {$this->rule->min}",
                    key: $key,
                    fullPath: $fullPath,
                );
            }
        }

        if ($this->rule->max !== null) {
            $tooHigh = $this->rule->isMaxInclusive() ? $value > $this->rule->max : $value >= $this->rule->max;
            if ($tooHigh) {
                $operator = $this->rule->isMaxInclusive() ? '<=' : '<';
                $errors[] = new ValidationError(
                    code: ValidationErrorCode::OUT_OF_RANGE,
                    programmerMessage: "Value of '{$key}' must be {$operator} {$this->rule->max}",
                    key: $key,
                    fullPath: $fullPath,
                );
            }
        }

        return $errors;
    }
}
