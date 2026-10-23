<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\ObjectType;

final class RecursiveFolderDTO extends RecursiveTreeItemDTO
{
    /**
     * @param list<RecursiveTreeItemDTO> $children
     */
    public function __construct(
        string $name,
        #[ListOf(
            value: RecursiveTreeItemDTO::class,
            discriminator: 'kind',
            mapping: [
                'folder' => RecursiveFolderDTO::class,
                'file' => RecursiveFileDTO::class,
            ],
            passDiscriminator: false
        )]
        public readonly array $children = [],
        #[ObjectType(
            discriminator: 'kind',
            mapping: [
                'folder' => RecursiveFolderDTO::class,
                'file' => RecursiveFileDTO::class,
            ],
            passDiscriminator: false
        )]
        public readonly ?RecursiveTreeItemDTO $pinned = null,
    ) {
        parent::__construct($name);
    }
}
