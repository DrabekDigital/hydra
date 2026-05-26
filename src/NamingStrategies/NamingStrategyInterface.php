<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\NamingStrategies;

/**
 * Interface for naming strategies that convert property names.
 */
interface NamingStrategyInterface
{
    /**
     * Convert property name for hydration (input key -> property name mapping).
     */
    public function toInputKey(string $propertyName): string;

    /**
     * Convert property name for dehydration (property name -> output key mapping).
     */
    public function toOutputKey(string $propertyName): string;
}
