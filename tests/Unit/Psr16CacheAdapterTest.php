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

    public function testClearKeepsForeignKeysInSharedPool(): void
    {
        $pool = new InMemoryPsr16Cache();
        $pool->set('app.session.abc', 'session-data');
        $pool->set('rate_limit.user_1', 42);
        $cache = new Psr16CacheAdapter($pool);

        $cache->set('ClassOne', 'hydrator', '<?php return new class {};');
        $cache->clear();

        $this->assertFalse($cache->has('ClassOne', 'hydrator'));
        $this->assertNull($cache->get('ClassOne', 'hydrator'));
        $this->assertSame('session-data', $pool->get('app.session.abc'));
        $this->assertSame(42, $pool->get('rate_limit.user_1'));
    }

    public function testItemsStoredAfterClearAreReachable(): void
    {
        $cache = new Psr16CacheAdapter(new InMemoryPsr16Cache());
        $code = '<?php return new class {};';

        $cache->set('ClassOne', 'hydrator', $code);
        $cache->clear();
        $cache->set('ClassOne', 'hydrator', $code);

        $this->assertTrue($cache->has('ClassOne', 'hydrator'));
        $this->assertNotNull($cache->get('ClassOne', 'hydrator'));

        $cache->clear();

        $this->assertFalse($cache->has('ClassOne', 'hydrator'));
    }

    public function testClearIsVisibleToAdapterInstancesCreatedAfterwards(): void
    {
        $pool = new InMemoryPsr16Cache();
        $first = new Psr16CacheAdapter($pool);

        $first->set('ClassOne', 'hydrator', '<?php return new class {};');
        $this->assertTrue(new Psr16CacheAdapter($pool)->has('ClassOne', 'hydrator'));

        $first->clear();

        $this->assertFalse(new Psr16CacheAdapter($pool)->has('ClassOne', 'hydrator'));
    }

    public function testCustomPrefixIsolatesInstancesSharingOnePool(): void
    {
        $pool = new InMemoryPsr16Cache();
        $first = new Psr16CacheAdapter($pool, 'hydra_a');
        $second = new Psr16CacheAdapter($pool, 'hydra_b');
        $code = '<?php return new class {};';

        $first->set('ClassOne', 'hydrator', $code);
        $second->set('ClassOne', 'hydrator', $code);

        $first->clear();

        $this->assertFalse($first->has('ClassOne', 'hydrator'));
        $this->assertTrue($second->has('ClassOne', 'hydrator'));
    }

    public function testEntriesAreNotReusedAfterGenerationKeyEviction(): void
    {
        $pool = new InMemoryPsr16Cache();
        new Psr16CacheAdapter($pool)->set('ClassOne', 'hydrator', '<?php return new class {};');

        $pool->delete('hydra.__generation');
        $cache = new Psr16CacheAdapter($pool);

        $this->assertFalse($cache->has('ClassOne', 'hydrator'));
        $this->assertNull($cache->get('ClassOne', 'hydrator'));
    }

    public function testInvalidGenerationTokenIsReplacedByRandomToken(): void
    {
        $pool = new InMemoryPsr16Cache();
        $pool->set('hydra.__generation', 1);
        $cache = new Psr16CacheAdapter($pool);

        $cache->set('ClassOne', 'hydrator', '<?php return new class {};');

        $this->assertTrue($cache->has('ClassOne', 'hydrator'));
        $this->assertIsString($pool->get('hydra.__generation'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $pool->get('hydra.__generation'));
    }

    public function testClearChangesGenerationEvenWhenGenerationKeyIsMissing(): void
    {
        $pool = new InMemoryPsr16Cache();
        $cache = new Psr16CacheAdapter($pool);

        $cache->clear();
        $first = $pool->get('hydra.__generation');
        $cache->clear();
        $second = $pool->get('hydra.__generation');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $first);
        $this->assertNotSame($first, $second);
    }

    public function testGenerationIsReadFromBackendOncePerInstance(): void
    {
        $pool = new InMemoryPsr16Cache();
        $cache = new Psr16CacheAdapter($pool);
        $code = '<?php return new class {};';

        $cache->set('ClassOne', 'hydrator', $code);
        $cache->has('ClassOne', 'hydrator');
        $cache->get('ClassOne', 'hydrator');
        $cache->invalidate('ClassOne', 'hydrator');
        $cache->clear();
        $cache->set('ClassOne', 'hydrator', $code);
        $cache->has('ClassOne', 'hydrator');

        $this->assertSame(1, $pool->reads['hydra.__generation']);
    }

    public function testTtlIsAppliedToEntriesButNotToGenerationKey(): void
    {
        $pool = new InMemoryPsr16Cache();
        $cache = new Psr16CacheAdapter($pool, ttl: 3600);

        $cache->set('ClassOne', 'hydrator', '<?php return new class {};');
        $cache->clear();

        $this->assertSame([3600], array_values(array_filter(
            $pool->ttls,
            static fn (string $key): bool => $key !== 'hydra.__generation',
            ARRAY_FILTER_USE_KEY,
        )));
        $this->assertNull($pool->ttls['hydra.__generation']);
    }

    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Psr16CacheAdapter(new InMemoryPsr16Cache(), 'hydra:{bad}');
    }
}

final class InMemoryPsr16Cache implements Psr16CacheInterface
{
    /** @var array<string, null|\DateInterval|int> */
    public array $ttls = [];

    /** @var array<string, int> */
    public array $reads = [];

    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->reads[$key] = ($this->reads[$key] ?? 0) + 1;

        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key], $this->ttls[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->ttls = [];

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
