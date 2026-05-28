<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Uuid;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class UuidRule
{
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function __construct(private readonly Uuid $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_UUID,
                programmerMessage: $this->rule->message ?? "String '{$key}' is not a valid UUID",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }
}
