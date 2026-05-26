<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit;

use DrabekDigital\Hydra\Caching\Psr16CacheAdapter;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface as Psr16CacheInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class Psr16CacheAdapterTest extends TestCase
{
    public function testSetAndGetWorks(): void
    {
        $cache = new Psr16CacheAdapter(new InMemoryPsr16Cache());
        $code = '<?php return new class { public function test(): string { return "hello"; } };';

        $cache->set('TestClass', 'hydrator', $code);

        $this->assertTrue($cache->has('TestClass', 'hydrator'));
        $hydrator = $cache->get('TestClass', 'hydrator');

        $this->assertNotNull($hydrator);
        $this->assertTrue(method_exists($hydrator, 'test'));
        $this->assertSame('hello', $hydrator->test());
    }

    public function testGetReturnsNullForMissingItems(): void
    {
        $cache = new Psr16CacheAdapter(new InMemoryPsr16Cache());
        $this->assertNull($cache->get('UnknownClass', 'hydrator'));
    }

    public function testInvalidateRemovesSingleItem(): void
    {
        $cache = new Psr16CacheAdapter(new InMemoryPsr16Cache());
        $code = '<?php return new class {};';

        $cache->set('TestClass', 'hydrator', $code);
        $this->assertTrue($cache->has('TestClass', 'hydrator'));

        $cache->invalidate('TestClass', 'hydrator');

        $this->assertFalse($cache->has('TestClass', 'hydrator'));
    }

    public function testClearRemovesAllItems(): void
    {
        $cache = new Psr16CacheAdapter(new InMemoryPsr16Cache());
        $code = '<?php return new class {};';

        $cache->set('ClassOne', 'hydrator', $code);
        $cache->set('ClassTwo', 'dehydrator', $code);
        $this->assertTrue($cache->has('ClassOne', 'hydrator'));
        $this->assertTrue($cache->has('ClassTwo', 'dehydrator'));

        $cache->clear();

        $this->assertFalse($cache->has('ClassOne', 'hydrator'));
        $this->assertFalse($cache->has('ClassTwo', 'dehydrator'));
    }
}

final class InMemoryPsr16Cache implements Psr16CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}
