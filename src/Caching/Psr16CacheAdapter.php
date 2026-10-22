<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Caching;

use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * PSR-16 adapter for generated hydrator/dehydrator code.
 *
 * Entries are stored under "{prefix}.{generation}.{hash}" keys. The generation is a random token
 * kept under "{prefix}.__generation" (without TTL); clear() only writes a new random token, so
 * entries of previous generations become unreachable and expire (see $ttl) or get evicted by
 * the backend. A missing or invalid token (e.g. evicted by LRU) is replaced by a new random one,
 * so entries stored before can never become reachable again.
 * Keys not owned by this adapter are never touched, which makes it safe to wrap an
 * application-wide pool. Adapters with different prefixes are cleared independently.
 *
 * The generation is read from the backend once per adapter instance; a clear() made by another
 * process becomes visible to adapter instances created afterwards.
 *
 * Security note:
 * Generated code is executed via eval(), so the backing cache storage must be treated as trusted.
 */
final class Psr16CacheAdapter implements CacheInterface
{
    private const string GENERATION_KEY_SUFFIX = '__generation';

    private ?string $generation = null;

    /**
     * @param string                 $prefix key prefix, allowed characters are A-Z, a-z, 0-9, "_" and "."
     * @param null|\DateInterval|int $ttl    TTL of generated code entries, so entries orphaned by clear() do not live forever
     *
     * @throws \InvalidArgumentException when the prefix contains characters not allowed in PSR-16 keys
     */
    public function __construct(
        private readonly Psr16CacheInterface $cache,
        private readonly string $prefix = 'hydra',
        private readonly \DateInterval|int|null $ttl = null,
    ) {
        if (preg_match('/^[A-Za-z0-9_.]+$/', $prefix) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid cache prefix "%s", allowed characters are A-Z, a-z, 0-9, "_" and ".".', $prefix));
        }
    }

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
        $this->cache->set($this->buildKey($className, $type), $code, $this->ttl);
    }

    public function invalidate(string $className, string $type): void
    {
        $this->cache->delete($this->buildKey($className, $type));
    }

    public function clear(): void
    {
        $this->generation = $this->storeNewGeneration();
    }

    private function buildKey(string $className, string $type): string
    {
        return $this->prefix.'.'.$this->getGeneration().'.'.md5($type.'|'.$className);
    }

    private function getGeneration(): string
    {
        if ($this->generation !== null) {
            return $this->generation;
        }

        $generation = $this->cache->get($this->getGenerationKey());
        if (!is_string($generation) || preg_match('/^[0-9a-f]{16}$/', $generation) !== 1) {
            $generation = $this->storeNewGeneration();
        }

        return $this->generation = $generation;
    }

    private function storeNewGeneration(): string
    {
        $generation = bin2hex(random_bytes(8));
        $this->cache->set($this->getGenerationKey(), $generation);

        return $generation;
    }

    private function getGenerationKey(): string
    {
        return $this->prefix.'.'.self::GENERATION_KEY_SUFFIX;
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
