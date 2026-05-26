<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Mappers;

/**
 * Interface for custom mappers.
 */
interface Mapper
{
    /**
     * Convert input data to the property value.
     *
     * @param mixed  $value    The raw input value
     * @param string $key      The property name
     * @param string $fullPath The full path to the property (for nested objects)
     *
     * @return mixed The converted value
     */
    public function hydrate(mixed $value, string $key, string $fullPath): mixed;

    /**
     * Convert property value to output data.
     *
     * @param mixed  $value    The property value
     * @param string $key      The property name
     * @param string $fullPath The full path to the property (for nested objects)
     *
     * @return mixed The converted output value
     */
    public function dehydrate(mixed $value, string $key, string $fullPath): mixed;
}
