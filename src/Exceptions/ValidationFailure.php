<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Exceptions;

use DrabekDigital\Hydra\Errors\ValidationError;

class ValidationFailure extends \Exception
{
    /**
     * @param array<ValidationError> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
