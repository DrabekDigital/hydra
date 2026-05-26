<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Exceptions;

use DrabekDigital\Hydra\Errors\HydrationError;

class HydrationFailure extends \Exception
{
    /**
     * @param array<HydrationError> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Hydration failed',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<HydrationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
