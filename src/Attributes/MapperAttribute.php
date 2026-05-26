<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

use DrabekDigital\Hydra\Mappers\Mapper;

/**
 * Interface for attributes that define custom mappers.
 *
 * Implement this interface to create shorthand attributes for your mappers.
 * This allows you to create semantic, reusable attributes instead of using
 * #[CustomMapper(mapper: YourMapper::class)] everywhere.
 *
 * Example:
 * ```php
 * #[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
 * final class Money implements MapperAttribute {
 *     public function getMapperClass(): string {
 *         return MoneyMapper::class;
 *     }
 * }
 * ```
 */
interface MapperAttribute
{
    /**
     * Get the mapper class to use for this property.
     *
     * @return class-string<Mapper>
     */
    public function getMapperClass(): string;
}
