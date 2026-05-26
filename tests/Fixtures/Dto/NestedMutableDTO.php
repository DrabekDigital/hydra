<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Mutable DTO with nested mutable child.
 */
class NestedMutableDTO
{
    public string $name;
    public MutableDTO $child;

    public function __construct(string $name, MutableDTO $child)
    {
        $this->name = $name;
        $this->child = $child;
    }
}
