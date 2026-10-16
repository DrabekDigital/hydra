<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Dto;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Attributes\Undefinable;

class FloatWideningDTO
{
    /**
     * @param list<float>              $samples
     * @param array<string, float>     $rates
     * @param array<array-key, float>  $weights
     * @param array<array-key, ?float> $optionalWeights
     */
    public function __construct(
        public readonly float $price,
        public readonly ?float $discount,
        #[ListOf('float')]
        public readonly array $samples,
        #[MapOf('string', 'float')]
        public readonly array $rates,
        #[ArrayOf('float')]
        public readonly array $weights,
        #[ArrayOf('float|null')]
        public readonly array $optionalWeights,
        public readonly float $ratio = 1.0,
        #[Undefinable]
        public readonly ?float $bonus = null,
    ) {}
}
