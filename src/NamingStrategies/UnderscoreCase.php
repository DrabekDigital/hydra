<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\NamingStrategies;

/**
 * Converts camelCase property names to/from snake_case keys.
 */
final class UnderscoreCase implements NamingStrategyInterface
{
    /**
     * Convert camelCase property name to snake_case input key.
     */
    public function toInputKey(string $propertyName): string
    {
        return $this->camelToSnake($propertyName);
    }

    /**
     * Convert camelCase property name to snake_case output key.
     */
    public function toOutputKey(string $propertyName): string
    {
        return $this->camelToSnake($propertyName);
    }

    private function camelToSnake(string $input): string
    {
        $converted = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);
        if ($converted === null) {
            return strtolower($input);
        }

        return strtolower($converted);
    }
}
