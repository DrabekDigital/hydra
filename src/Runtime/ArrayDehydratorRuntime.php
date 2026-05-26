<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Runtime;

interface ArrayDehydratorRuntime
{
    /**
     * @param array<object> $objects
     *
     * @return array<array<string, mixed>>
     */
    public function dehydrate(array $objects, string $path = ''): array;

    public function setNestedDehydrator(string $className, object $dehydrator): void;
}
