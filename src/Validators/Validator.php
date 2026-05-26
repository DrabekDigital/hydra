<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Validators;

use DrabekDigital\Hydra\Errors\ValidationError;

/**
 * Interface for custom validators.
 */
interface Validator
{
    /**
     * Validate a value.
     *
     * @param mixed  $value    The value to validate
     * @param string $key      The property name
     * @param string $fullPath The full path to the property (for nested objects)
     *
     * @return null|ValidationError Return null if valid, ValidationError if invalid
     */
    public function validate(mixed $value, string $key, string $fullPath): ?ValidationError;
}
