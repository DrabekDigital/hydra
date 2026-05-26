<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Caching;

use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * PSR-16 adapter for generated hydrator/dehydrator code.
 *
 * Security note:
 * Generated code is executed via eval(), so the backing cache storage must be treated as trusted.
 */
final class Psr16CacheAdapter implements CacheInterface
{
    public function __construct(
        private readonly Psr16CacheInterface $cache,
    ) {}

    public function has(string $className, string $type): bool
    {
        return $this->cache->has($this->buildKey($className, $type));
    }

    public function get(string $className, string $type): ?object
    {
        $code = $this->cache->get($this->buildKey($className, $type));
        if (!is_string($code) || $code === '') {
            return null;
        }

        $object = $this->executeCode($code);

        return is_object($object) ? $object : null;
    }

    public function set(string $className, string $type, string $code): void
    {
        $this->cache->set($this->buildKey($className, $type), $code);
    }

    public function invalidate(string $className, string $type): void
    {
        $this->cache->delete($this->buildKey($className, $type));
    }

    public function clear(): void
    {
        $this->cache->clear();
    }

    private function buildKey(string $className, string $type): string
    {
        return 'hydra.'.md5($type.'|'.$className);
    }

    private function executeCode(string $code): mixed
    {
        // Stored code may include the opening tag when produced by generators.
        $trimmedCode = ltrim($code);
        if (str_starts_with($trimmedCode, '<?php')) {
            $trimmedCode = ltrim(substr($trimmedCode, 5));
        }

        // Execute only code coming from a trusted cache backend.
        return eval($trimmedCode);
    }
}
