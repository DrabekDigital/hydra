<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Exceptions;

use DrabekDigital\Hydra\Errors\DehydrationError;
use DrabekDigital\Hydra\Errors\HydrationError;

/**
 * Thrown from custom formatter closures to signal a controlled formatting failure.
 */
class FormatterException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?DehydrationError $dehydrationError = null,
        private readonly ?HydrationError $hydrationError = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function withDehydrationError(DehydrationError $error): self
    {
        return new self($error->programmerMessage, dehydrationError: $error);
    }

    public function getDehydrationError(): ?DehydrationError
    {
        return $this->dehydrationError;
    }

    public static function withHydrationError(HydrationError $error): self
    {
        return new self($error->programmerMessage, hydrationError: $error);
    }

    public function getHydrationError(): ?HydrationError
    {
        return $this->hydrationError;
    }
}
