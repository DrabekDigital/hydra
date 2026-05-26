<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Runtime;

interface HydratorRuntime
{
    /**
     * @param array<string, mixed> $data
     */
    public function hydrate(array $data, string $path = ''): object;

    public function setNestedHydrator(string $className, object $hydrator): void;

    public function setCustomMapper(string $className, object $mapper): void;

    public function setFormatter(string $propertyName, ?\Closure $hydrate, ?\Closure $dehydrate): void;
}
