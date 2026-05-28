<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Hostname;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class HostnameRule
{
    public function __construct(private readonly Hostname $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (!$this->isValidHostname($value)) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_HOSTNAME,
                programmerMessage: $this->rule->message ?? "String '{$key}' is not a valid hostname",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }

    private function isValidHostname(string $value): bool
    {
        if (strlen($value) > 253 || $value === '') {
            return false;
        }

        if (!preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]{1,63}\.)*[a-z0-9-]{1,63}$/i', $value)) {
            return false;
        }

        if (!$this->rule->requireTld) {
            return true;
        }

        return str_contains($value, '.');
    }
}
