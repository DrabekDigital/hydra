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
        $this->assertFalse($this->cache->has('InvalidPayload', 'hydrator'));
    }

    public function testGetReturnsNullAndRemovesFileWithParseError(): void
    {
        $this->cache->set('Truncated', 'hydrator', '<?php return new class { public function test(');

        $this->assertNull($this->cache->get('Truncated', 'hydrator'));
        $this->assertFalse($this->cache->has('Truncated', 'hydrator'));
    }

    public function testGetReturnsNullAndRemovesFileThrowingError(): void
    {
        $this->cache->set('Throwing', 'hydrator', '<?php return \DrabekDigital\Hydra\Missing\undefinedFunction();');

        $this->assertNull($this->cache->get('Throwing', 'hydrator'));
        $this->assertFalse($this->cache->has('Throwing', 'hydrator'));
    }

    // =========================================================================
    // Atomic Writes & Failure Handling
    // =========================================================================

    public function testSetLeavesNoTemporaryFiles(): void
    {
        $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');
        $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');

        $files = array_values(array_diff(scandir($this->cacheDir.'/hydrator') ?: [], ['.', '..']));

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.php', $files[0]);
    }

    public function testSetCreatesFileReadableByOthers(): void
    {
        $previousUmask = umask(0o022);

        try {
            $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');
        } finally {
            umask($previousUmask);
        }

        $files = glob($this->cacheDir.'/hydrator/*.php') ?: [];
        $this->assertCount(1, $files);
        $this->assertSame(0o644, fileperms($files[0]) & 0o777);
    }

    public function testSetToExistingTypeDirectoryCreatedByAnotherProcessDoesNotFail(): void
    {
        mkdir($this->cacheDir.'/hydrator', 0o755, true);

        $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');

        $this->assertNotNull($this->cache->get('TestClass', 'hydrator'));
    }

    public function testConstructionWithDirectoryCreatedConcurrentlyDoesNotFail(): void
    {
        $directory = $this->cacheDir.'/nested/deeper';
        $first = new FileCache($directory);
        $second = new FileCache($directory);
        $first->clear();
        $second->clear();

        $this->assertDirectoryExists($directory);
    }

    public function testConstructionThrowsWhenDirectoryCannotBeCreated(): void
    {
        $file = $this->cacheDir.'/not-a-directory';
        file_put_contents($file, '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create cache directory');
        new FileCache($file.'/cache');
    }

    public function testSetThrowsAndCleansUpWhenRenameFails(): void
    {
        $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');
        $files = glob($this->cacheDir.'/hydrator/*.php') ?: [];
        $this->assertCount(1, $files);
        unlink($files[0]);
        mkdir($files[0]);

        try {
            $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unable to move cache file', $e->getMessage());
        }

        $this->assertSame([], glob($this->cacheDir.'/hydrator/*.tmp') ?: []);
    }

    public function testSetThrowsWhenDirectoryIsReadOnly(): void
    {
        $directory = $this->cacheDir.'/hydrator';
        mkdir($directory, 0o755, true);
        chmod($directory, 0o555);

        try {
            if (is_writable($directory)) {
                $this->markTestSkipped('Directory permissions are not enforced (running as root?)');
            }

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to write cache file');
            $this->cache->set('TestClass', 'hydrator', '<?php return new class {};');
        } finally {
            chmod($directory, 0o755);
        }
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
