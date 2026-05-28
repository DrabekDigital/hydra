<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Choice;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class ChoiceRule
{
    public function __construct(private readonly Choice $rule) {}

    /** @return list<ValidationError> */
    public function validate(mixed $value, string $key, string $fullPath): array
    {
        if (!$this->isAllowed($value)) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_CHOICE,
                programmerMessage: $this->rule->message ?? "Value '{$key}' is not one of allowed choices",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }

    private function isAllowed(mixed $value): bool
    {
        foreach ($this->collectChoices() as $choice) {
            if ($this->matchesChoice($value, $choice)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<bool|float|int|string|\UnitEnum> */
    private function collectChoices(): array
    {
        $choices = $this->rule->choices;

        if ($this->rule->enumClass !== null) {
            /** @var list<\UnitEnum> $cases */
            $cases = $this->rule->enumClass::cases();
            array_push($choices, ...$cases);
        }

        return $choices;
    }

    private function matchesChoice(mixed $value, mixed $choice): bool
    {
        if ($choice instanceof \UnitEnum) {
            if ($value instanceof \UnitEnum) {
                return $value === $choice;
            }

            if ($value === $choice->name) {
                return true;
            }

            if ($choice instanceof \BackedEnum) {
                return $this->rule->strict
                    ? $value === $choice->value
                    : $value == $choice->value;
            }

            return false;
        }

        if ($value instanceof \BackedEnum) {
            return $this->rule->strict
                ? $value->value === $choice
                : $value->value == $choice;
        }

        return $this->rule->strict
            ? $value === $choice
            : $value == $choice;
    }
}
