<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\NamingStrategies;

/**
 * Default 1:1 naming strategy - property names are used as-is.
 */
final class DefaultStrategy implements NamingStrategyInterface
{
    public function toInputKey(string $propertyName): string
    {
        return $propertyName;
    }

    public function toOutputKey(string $propertyName): string
    {
        return $propertyName;
    }
}
