<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

final class RecursiveFileDTO extends RecursiveTreeItemDTO
{
    public function __construct(
        string $name,
        public readonly int $size,
    ) {
        parent::__construct($name);
    }
}
