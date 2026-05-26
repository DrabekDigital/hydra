<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\URL;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class UrlRule
{
    private readonly URL $rule;

    public function __construct(URL $rule)
    {
        $this->rule = $rule;
    }

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_URL,
                programmerMessage: "String '{$key}' is not a valid URL",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        $errors = [];
        $parts = parse_url($value);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;

        if ($this->rule->requireScheme && ($scheme === null || $scheme === '')) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::INVALID_URL,
                programmerMessage: "URL '{$key}' must contain a scheme",
                key: $key,
                fullPath: $fullPath,
            );
        }

        if ($this->rule->allowedSchemes !== null && $scheme !== null && !in_array($scheme, $this->rule->allowedSchemes, true)) {
            $errors[] = new ValidationError(
                code: ValidationErrorCode::INVALID_URL,
                programmerMessage: "URL '{$key}' has unsupported scheme '{$scheme}'",
                key: $key,
                fullPath: $fullPath,
            );
        }

        return $errors;
    }
}
