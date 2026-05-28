<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

/**
 * Validation attribute with declared value-type and null-handling semantics.
 */
interface TypedValidationAttribute
{
    /**
     * @return list<ValidationValueType>
     */
    public function supportedValueTypes(): array;

    public function nullBehavior(): NullValidationBehavior;
}
