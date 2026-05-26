<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * DTO with no constructor - just public properties.
 */
class NoConstructorDTO
{
    public string $title;
    public int $count;
    public ?string $description = null;
    public bool $active = true;
}
