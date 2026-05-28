<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes\Validation;

use DrabekDigital\Hydra\Validators\AttributeValidator;
use DrabekDigital\Hydra\Validators\Validator;

/**
 * Validation attribute that declares which custom validator handles it.
 */
interface ValidationConstraint
{
    /**
     * @return class-string<AttributeValidator|Validator>
     */
    public function validatorClass(): string;
}
