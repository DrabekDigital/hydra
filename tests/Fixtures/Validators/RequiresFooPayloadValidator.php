<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Validators;

use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Validators\AttributeValidator;

final class RequiresFooPayloadValidator implements AttributeValidator
{
    public function validateWithConstraint(
        mixed $value,
        string $key,
        string $fullPath,
        ValidationConstraint $constraint,
    ): ?ValidationError {
        if (!$constraint instanceof RequiresFooPayload) {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: 'Unexpected constraint type for RequiresFooPayloadValidator',
                key: $key,
                fullPath: $fullPath,
            );
        }

        if (!$value instanceof ValidationCustomPayload || $value->value !== $constraint->expectedValue) {
            return new ValidationError(
                code: ValidationErrorCode::CUSTOM,
                programmerMessage: "Expected payload value \"{$constraint->expectedValue}\"",
                key: $key,
                fullPath: $fullPath,
            );
        }

        return null;
    }
}
