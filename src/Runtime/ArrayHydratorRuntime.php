<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Runtime;

interface ArrayHydratorRuntime
{
    /**
     * @param array<int, mixed> $data
     *
     * @return array<object>
     */
    public function hydrate(array $data, string $path = ''): array;

    public function setNestedHydrator(string $className, object $hydrator): void;
}
