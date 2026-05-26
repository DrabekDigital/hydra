<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Runtime;

interface DehydratorRuntime
{
    /**
     * @return array<string, mixed>
     */
    public function dehydrate(object $object, string $path = ''): array;

    public function setNestedDehydrator(string $className, object $dehydrator): void;

    public function setCustomMapper(string $className, object $mapper): void;

    public function setFormatter(string $propertyName, ?\Closure $hydrate, ?\Closure $dehydrate): void;
}
