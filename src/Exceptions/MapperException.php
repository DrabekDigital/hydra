<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Exceptions;

/**
 * Thrown from a Mapper implementation to signal a controlled mapping failure.
 *
 * Use this for expected, recoverable errors (type mismatches, validation failures).
 * Any \Throwable is also caught as a fallback, but MapperException is the preferred signal.
 */
class MapperException extends \Exception {}
