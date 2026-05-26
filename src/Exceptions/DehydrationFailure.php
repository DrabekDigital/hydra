<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Exceptions;

use DrabekDigital\Hydra\Errors\DehydrationError;

class DehydrationFailure extends \Exception
{
    /**
     * @param array<DehydrationError> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Dehydration failed',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<DehydrationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
