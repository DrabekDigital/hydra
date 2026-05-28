<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

use DrabekDigital\Hydra\Validators\Validator;

/**
 * Specifies a custom validator class for a property.
 *
 * Consider implementing {@see ValidationConstraint} on your own attribute instead.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class CustomValidator implements ValidationConstraint
{
    /**
     * @param class-string<Validator> $validator
     */
    public function __construct(
        public readonly string $validator,
    ) {}

    public function validatorClass(): string
    {
        return $this->validator;
    }
}
