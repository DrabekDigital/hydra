<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

use DrabekDigital\Hydra\Mappers\Mapper;

/**
 * Specifies a custom mapper class for a property.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class CustomMapper implements MapperAttribute
{
    /**
     * @param class-string<Mapper> $mapper
     */
    public function __construct(
        public readonly string $mapper,
    ) {}

    public function getMapperClass(): string
    {
        return $this->mapper;
    }
}
