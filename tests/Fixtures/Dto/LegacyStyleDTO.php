<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

/**
 * Old-school PHP DTO style: public properties assigned in constructor.
 */
class LegacyStyleDTO
{
    public string $title;
    public string $description;
    public int $priority;
    public bool $active;

    public function __construct(
        string $title,
        string $description,
        int $priority = 0,
        bool $active = true,
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->priority = $priority;
        $this->active = $active;
    }
}
