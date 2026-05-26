<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

use DrabekDigital\Hydra\NamingStrategies\NamingStrategyInterface;

/**
 * Specifies a naming strategy for a class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NamingStrategy
{
    public function __construct(
        public readonly NamingStrategyInterface $strategy,
    ) {}
}
