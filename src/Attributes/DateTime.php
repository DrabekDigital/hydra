<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final class DateTime
{
    public function __construct(
        public readonly string $format = \DateTimeInterface::ATOM,
        public readonly ?string $timezone = null,
        public readonly bool $immutable = true,
    ) {}
}
