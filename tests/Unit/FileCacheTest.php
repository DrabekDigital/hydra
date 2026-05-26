<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit;

use DrabekDigital\Hydra\Caching\FileCache;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/hydra_test_cache_'.uniqid();
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->cacheDir);
    }

    // =========================================================================
    // Basic Operations
    // =========================================================================

    public function testCreatesCacheDirectoryOnConstruction(): void
    {
        $this->assertDirectoryExists($this->cacheDir);
    }

    public function testConstructionWithExistingDirectoryDoesNotFail(): void
    {
        $existing = sys_get_temp_dir().'/hydra_existing_cache_'.uniqid();
        mkdir($existing, 0o755, true);

        $cache = new FileCache($existing);

        $this->assertTrue($cache->has('missing', 'hydrator') === false);
        $this->deleteDirectory($existing);
    }

    public function testHasReturnsFalseForMissingCache(): void
    {
        $this->assertFalse($this->cache->has('NonExistent', 'hydrator'));
    }

    public function testSetAndGetWorks(): void
    {
        $code = '<?php return new class { public function test(): string { return "hello"; } };';

        $this->cache->set('TestClass', 'hydrator', $code);

        $this->assertTrue($this->cache->has('TestClass', 'hydrator'));

        $object = $this->cache->get('TestClass', 'hydrator');
        $this->assertNotNull($object);
        $this->assertTrue(method_exists($object, 'test'));
        $this->assertSame('hello', $object->test());
    }

    public function testGetReturnsNullForMissing(): void
    {
        $this->assertNull($this->cache->get('NonExistent', 'hydrator'));
    }

    public function testGetReturnsNullWhenCachedFileDoesNotReturnObject(): void
    {
        $this->cache->set('InvalidPayload', 'hydrator', '<?php return [1, 2, 3];');

        $this->assertNull($this->cache->get('InvalidPayload', 'hydrator'));
    }

    public function testInvalidateRemovesCache(): void
    {
        $code = '<?php return new class {};';
        $this->cache->set('TestClass', 'hydrator', $code);

        $this->assertTrue($this->cache->has('TestClass', 'hydrator'));

        $this->cache->invalidate('TestClass', 'hydrator');

        $this->assertFalse($this->cache->has('TestClass', 'hydrator'));
    }

    public function testClearRemovesAllCache(): void
    {
        $code = '<?php return new class {};';
        $this->cache->set('Class1', 'hydrator', $code);
        $this->cache->set('Class2', 'hydrator', $code);
        $this->cache->set('Class1', 'dehydrator', $code);

        $this->cache->clear();

        $this->assertFalse($this->cache->has('Class1', 'hydrator'));
        $this->assertFalse($this->cache->has('Class2', 'hydrator'));
        $this->assertFalse($this->cache->has('Class1', 'dehydrator'));
    }

    public function testClearRecreatesDirectoryWhenItIsMissing(): void
    {
        $this->deleteDirectory($this->cacheDir);
        $this->cache->clear();

        $this->assertDirectoryExists($this->cacheDir);
    }

    // =========================================================================
    // Cache Types
    // =========================================================================

    public function testDifferentTypesAreSeparate(): void
    {
        $hydratorCode = '<?php return new class { public function type(): string { return "hydrator"; } };';
        $dehydratorCode = '<?php return new class { public function type(): string { return "dehydrator"; } };';

        $this->cache->set('TestClass', 'hydrator', $hydratorCode);
        $this->cache->set('TestClass', 'dehydrator', $dehydratorCode);

        $hydrator = $this->cache->get('TestClass', 'hydrator');
        $dehydrator = $this->cache->get('TestClass', 'dehydrator');

        $this->assertNotNull($hydrator);
        $this->assertNotNull($dehydrator);
        $this->assertTrue(method_exists($hydrator, 'type'));
        $this->assertTrue(method_exists($dehydrator, 'type'));
        $this->assertSame('hydrator', $hydrator->type());
        $this->assertSame('dehydrator', $dehydrator->type());
    }

    // =========================================================================
    // Namespaced Classes
    // =========================================================================

    public function testHandlesNamespacedClassNames(): void
    {
        $code = '<?php return new class {};';

        $this->cache->set('App\Models\User', 'hydrator', $code);

        $this->assertTrue($this->cache->has('App\Models\User', 'hydrator'));
        $this->assertNotNull($this->cache->get('App\Models\User', 'hydrator'));
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testInvalidateNonExistentDoesNotThrow(): void
    {
        // Should not throw
        $this->cache->invalidate('NonExistent', 'hydrator');
        $this->assertFalse($this->cache->has('NonExistent', 'hydrator'));
    }

    public function testOverwriteExistingCache(): void
    {
        $code1 = '<?php return new class { public function value(): int { return 1; } };';
        $code2 = '<?php return new class { public function value(): int { return 2; } };';

        $this->cache->set('TestClass', 'hydrator', $code1);
        $obj1 = $this->cache->get('TestClass', 'hydrator');
        $this->assertNotNull($obj1);
        $this->assertTrue(method_exists($obj1, 'value'));
        $this->assertSame(1, $obj1->value());

        $this->cache->set('TestClass', 'hydrator', $code2);
        $obj2 = $this->cache->get('TestClass', 'hydrator');
        $this->assertNotNull($obj2);
        $this->assertTrue(method_exists($obj2, 'value'));
        $this->assertSame(2, $obj2->value());
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
