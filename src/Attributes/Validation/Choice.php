<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validates that a value belongs to allowed choices.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class Choice implements TypedValidationAttribute
{
    /**
     * @param list<bool|float|int|string|\UnitEnum> $choices
     * @param null|class-string<\UnitEnum>          $enumClass
     */
    public function __construct(
        public readonly array $choices = [],
        public readonly ?string $enumClass = null,
        public readonly bool $strict = true,
        public readonly ?string $message = null,
        public readonly NullValidationBehavior $onNull = NullValidationBehavior::SkipWhenNull,
    ) {
        if ($this->choices === [] && $this->enumClass === null) {
            throw new \InvalidArgumentException('Choice requires non-empty choices or enumClass.');
        }

        if ($this->enumClass !== null && !enum_exists($this->enumClass)) {
            throw new \InvalidArgumentException("Choice enumClass '{$this->enumClass}' does not exist.");
        }
    }

    public function supportedValueTypes(): array
    {
        if ($this->enumClass !== null) {
            return [ValidationValueType::Enum];
        }

        return [ValidationValueType::Mixed];
    }

    public function nullBehavior(): NullValidationBehavior
    {
        return $this->onNull;
    }
}
