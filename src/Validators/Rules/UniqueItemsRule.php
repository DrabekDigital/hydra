<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class UniqueItemsRule
{
    public function __construct(private readonly UniqueItems $rule) {}

    /**
     * @param array<mixed> $value
     *
     * @return list<ValidationError>
     */
    public function validate(array $value, string $key, string $fullPath): array
    {
        if ($this->rule->comparator === null) {
            foreach ($value as $item) {
                if ($this->containsNonNativelyComparableValue($item)) {
                    return [new ValidationError(
                        code: ValidationErrorCode::UNIQUE_ITEMS_COMPARATOR_REQUIRED,
                        programmerMessage: "Array '{$key}' contains values that require a UniqueItems comparator",
                        key: $key,
                        fullPath: $fullPath,
                    )];
                }
            }
        }

        $comparator = $this->rule->comparator;
        $count = count($value);

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $isEqual = $comparator !== null
                    ? $comparator($value[$i], $value[$j]) === true
                    : $value[$i] === $value[$j];

                if ($isEqual) {
                    return [new ValidationError(
                        code: ValidationErrorCode::NOT_UNIQUE,
                        programmerMessage: "Array '{$key}' contains duplicate values",
                        key: $key,
                        fullPath: $fullPath,
                    )];
                }
            }
        }

        return [];
    }

    private function containsNonNativelyComparableValue(mixed $item): bool
    {
        if ($item instanceof \UnitEnum) {
            return false;
        }

        if (is_object($item) || is_resource($item)) {
            return true;
        }

        if (!is_array($item)) {
            return false;
        }

        foreach ($item as $nested) {
            if ($this->containsNonNativelyComparableValue($nested)) {
                return true;
            }
        }

        return false;
    }
}
