<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators\Rules;

use DrabekDigital\Hydra\Attributes\Validation\Ip;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;

final class IpRule
{
    public function __construct(private readonly Ip $rule) {}

    /** @return list<ValidationError> */
    public function validate(string $value, string $key, string $fullPath): array
    {
        if (!$this->isValidIp($value)) {
            return [new ValidationError(
                code: ValidationErrorCode::INVALID_IP,
                programmerMessage: $this->rule->message ?? "String '{$key}' is not a valid IP address",
                key: $key,
                fullPath: $fullPath,
            )];
        }

        return [];
    }

    private function isValidIp(string $value): bool
    {
        if ($this->rule->allowV4 && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }

        if ($this->rule->allowV6 && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return true;
        }

        return false;
    }
}
