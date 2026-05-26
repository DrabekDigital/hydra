<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Caching;

interface CacheInterface
{
    /**
     * Check if a cached item exists for the given class.
     */
    public function has(string $className, string $type): bool;

    /**
     * Get the cached hydrator/dehydrator object.
     */
    public function get(string $className, string $type): ?object;

    /**
     * Store generated PHP code for a class.
     */
    public function set(string $className, string $type, string $code): void;

    /**
     * Invalidate cache for a specific class.
     */
    public function invalidate(string $className, string $type): void;

    /**
     * Clear all cached items.
     */
    public function clear(): void;
}
