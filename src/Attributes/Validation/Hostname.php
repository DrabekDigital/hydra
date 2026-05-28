<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a string is a hostname.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Hostname implements TypedValidationAttribute
{
    public function __construct(
        public readonly bool $requireTld = true,
        public readonly ?string $message = null,
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {}

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::String];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
