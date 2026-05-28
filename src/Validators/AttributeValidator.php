<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators;

use DrabekDigital\Hydra\Attributes\Validation\ValidationConstraint;
use DrabekDigital\Hydra\Errors\ValidationError;

/**
 * Custom validator that receives the constraint attribute instance from the property.
 */
interface AttributeValidator
{
    public function validateWithConstraint(
        mixed $value,
        string $key,
        string $fullPath,
        ValidationConstraint $constraint,
    ): ?ValidationError;
}
