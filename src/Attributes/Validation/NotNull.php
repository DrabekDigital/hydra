<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a value is not null.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class NotNull implements TypedValidationAttribute
{
    public function __construct(
        public readonly ?string $message = null,
    ) {}

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::Mixed];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return NullValidationBehavior::ValidateAlways;
    }
}
