<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that an array contains only unique items.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class UniqueItems implements TypedValidationAttribute
{
    /**
     * @param null|\Closure $comparator Custom comparator function (a, b) => bool
     */
    public function __construct(
        public readonly ?\Closure $comparator = null,
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {}

    public function supportedValueTypes(): array
    {
        return [ValidationValueType::Array];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
