<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Fixtures\Collisions;

use DrabekDigital\Hydra\Attributes\ListOf;

readonly class HydratorRuntime
{
    /**
     * @param list<HydrationError> $history
     */
    public function __construct(
        public HydrationError $hydrationError,
        public DehydrationError $dehydrationError,
        #[ListOf(HydrationError::class)]
        public array $history,
        public HydrationErrorCode $code = HydrationErrorCode::Missing,
    ) {}
}
