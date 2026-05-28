<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string is an IP address.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Ip implements TypedValidationAttribute
{
    public function __construct(
        public readonly bool $allowV4 = true,
        public readonly bool $allowV6 = true,
        public readonly ?string $message = null,
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {
        if (!$allowV4 && !$allowV6) {
            throw new \InvalidArgumentException('Ip validation must allow at least one of IPv4 or IPv6');
        }
    }

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::String];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
