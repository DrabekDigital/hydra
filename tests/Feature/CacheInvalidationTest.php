<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\CodeGen\GeneratorVersion;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NullableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CacheInvalidationTest extends TestCase
{
    // =========================================================================
    // Structure Hash Tests
    // =========================================================================

    public function testStructureHashIsConsistent(): void
    {
        $analyzer = new ClassAnalyzer();

        $hash1 = $analyzer->getStructureHash(SimpleDTO::class);
        $hash2 = $analyzer->getStructureHash(SimpleDTO::class);

        $this->assertSame($hash1, $hash2);
    }

    public function testStructureHashIs12Characters(): void
    {
        $analyzer = new ClassAnalyzer();
        $hash = $analyzer->getStructureHash(SimpleDTO::class);

        $this->assertSame(12, strlen($hash));
    }

    public function testDifferentClassesHaveDifferentHashes(): void
    {
        $analyzer = new ClassAnalyzer();

        $hash1 = $analyzer->getStructureHash(SimpleDTO::class);
        $hash2 = $analyzer->getStructureHash(NullableDTO::class);

        $this->assertNotSame($hash1, $hash2);
    }

    // =========================================================================
    // Cache File Naming
    // =========================================================================

    public function testCacheFilesIncludeStructureHash(): void
    {
        // Trigger hydration to create cache
        $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);

        // Check cache files
        $cacheFiles = glob(self::$cacheDir.'/hydrator/*.php');
        $this->assertNotEmpty($cacheFiles);

        // File should contain class name and hash
        $filename = basename(($cacheFiles ?: [])[0]);
        $this->assertStringContainsString('SimpleDTO', $filename);

        // Should have format: Namespace_ClassName_hash_md5.php (underscores replace backslashes)
        $this->assertMatchesRegularExpression('/^.+_[a-f0-9]{12}_[a-f0-9]{32}\.php$/', $filename);
    }

    // =========================================================================
    // Cache Clear
    // =========================================================================

    public function testClearCacheRemovesFiles(): void
    {
        // Create some cache
        $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);
        $this->assertNotEmpty(glob(self::$cacheDir.'/hydrator/*.php'));

        // Clear
        $this->hydrator->clearCache();

        // Should be empty (or directory removed)
        $files = glob(self::$cacheDir.'/hydrator/*.php');
        $this->assertEmpty($files ?: []);
    }

    public function testClearCacheForSpecificClassRemovesHydratorCacheFile(): void
    {
        $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);
        $this->hydrator->hydrateArray([['name' => 'John', 'age' => 30]], className: SimpleDTO::class);
        $filesBefore = glob(self::$cacheDir.'/hydrator/*.php');
        $this->assertNotEmpty($filesBefore ?: []);
        $arrayFilesBefore = glob(self::$cacheDir.'/array_hydrator/*.php');
        $this->assertNotEmpty($arrayFilesBefore ?: []);

        $this->hydrator->clearCache(SimpleDTO::class);

        $filesAfter = glob(self::$cacheDir.'/hydrator/*.php');
        $this->assertEmpty($filesAfter ?: []);
        $arrayFilesAfter = glob(self::$cacheDir.'/array_hydrator/*.php');
        $this->assertEmpty($arrayFilesAfter ?: []);
    }

    public function testClearCacheForSpecificClassRemovesDehydratorCacheFile(): void
    {
        $this->dehydrator->dehydrate(new SimpleDTO('John', 30));
        $this->dehydrator->dehydrateArray([new SimpleDTO('John', 30)]);
        $filesBefore = glob(self::$cacheDir.'/dehydrator/*.php');
        $this->assertNotEmpty($filesBefore ?: []);
        $arrayFilesBefore = glob(self::$cacheDir.'/array_dehydrator/*.php');
        $this->assertNotEmpty($arrayFilesBefore ?: []);

        $this->dehydrator->clearCache(SimpleDTO::class);

        $filesAfter = glob(self::$cacheDir.'/dehydrator/*.php');
        $this->assertEmpty($filesAfter ?: []);
        $arrayFilesAfter = glob(self::$cacheDir.'/array_dehydrator/*.php');
        $this->assertEmpty($arrayFilesAfter ?: []);
    }

    public function testClearCacheForSpecificClassRemovesDiscriminatedArrayHydratorCacheFile(): void
    {
        $this->hydrator->hydrateArray(
            [['type' => 'simple', 'name' => 'John', 'age' => 30]],
            discriminator: 'type',
            mapping: [
                'simple' => SimpleDTO::class,
                'nullable' => NullableDTO::class,
            ]
        );

        $arrayFilesBefore = glob(self::$cacheDir.'/array_hydrator/*.php');
        $this->assertNotEmpty($arrayFilesBefore ?: []);

        $this->hydrator->clearCache(SimpleDTO::class);

        $arrayFilesAfter = glob(self::$cacheDir.'/array_hydrator/*.php');
        $this->assertEmpty($arrayFilesAfter ?: []);
    }

    public function testClearCacheForSpecificClassRemovesMultiClassArrayDehydratorCacheFile(): void
    {
        $this->dehydrator->dehydrateArray([
            new SimpleDTO('John', 30),
            new NullableDTO(name: null, age: 22),
        ]);

        $arrayFilesBefore = glob(self::$cacheDir.'/array_dehydrator/*.php');
        $this->assertNotEmpty($arrayFilesBefore ?: []);

        $this->dehydrator->clearCache(SimpleDTO::class);

        $arrayFilesAfter = glob(self::$cacheDir.'/array_dehydrator/*.php');
        $this->assertEmpty($arrayFilesAfter ?: []);
    }

    // =========================================================================
    // Generator Version
    // =========================================================================

    public function testCacheFilesArePrefixedWithGeneratorVersion(): void
    {
        $this->runAllCachedOperations($this->hydrator, $this->dehydrator);

        $files = $this->cacheFiles();
        $this->assertCount(5, $files);
        foreach ($files as $file) {
            $this->assertStringStartsWith('v'.GeneratorVersion::GENERATOR_VERSION.'_', basename($file));
        }
    }

    public function testCacheEntriesFromOtherGeneratorVersionAreNotReused(): void
    {
        $this->runAllCachedOperations(
            new Hydrator($this->cache, generatorVersion: 'old'),
            new Dehydrator($this->cache, generatorVersion: 'old'),
        );
        $staleFiles = $this->cacheFiles();
        $this->assertCount(5, $staleFiles);

        // Simulate stale code generated by a previous release
        foreach ($staleFiles as $file) {
            file_put_contents($file, '<?php return new \stdClass();');
        }

        try {
            (new Hydrator($this->cache, generatorVersion: 'old'))->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);
            $this->fail('Stale cache entry should be loaded under the old generator version');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Invalid cached hydrator', $e->getMessage());
        }

        $this->runAllCachedOperations(new Hydrator($this->cache), new Dehydrator($this->cache));

        $this->assertCount(10, $this->cacheFiles());
    }

    public function testClearCacheForSpecificClassUsesGeneratorVersion(): void
    {
        $hydrator = new Hydrator($this->cache, generatorVersion: '2.0-test');
        $dehydrator = new Dehydrator($this->cache, generatorVersion: '2.0-test');
        $this->runAllCachedOperations($hydrator, $dehydrator);
        $this->assertCount(5, $this->cacheFiles());

        $hydrator->clearCache(SimpleDTO::class);
        $dehydrator->clearCache(SimpleDTO::class);

        $this->assertSame([], $this->cacheFiles());
    }

    public function testClearCacheRemovesEntriesOfAllGeneratorVersions(): void
    {
        $this->runAllCachedOperations(
            new Hydrator($this->cache, generatorVersion: 'old'),
            new Dehydrator($this->cache, generatorVersion: 'old'),
        );
        $this->runAllCachedOperations($this->hydrator, $this->dehydrator);
        $this->assertCount(10, $this->cacheFiles());

        $this->hydrator->clearCache();

        $this->assertSame([], $this->cacheFiles());
    }

    public function testInvalidGeneratorVersionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Hydrator($this->cache, generatorVersion: '../1');
    }

    // =========================================================================
    // In-Memory Cache
    // =========================================================================

    public function testHydratorUsesInMemoryCache(): void
    {
        // First hydration
        $dto1 = $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);

        // Remove file cache
        foreach (glob(self::$cacheDir.'/hydrator/*.php') ?: [] as $file) {
            unlink($file);
        }

        // Second hydration should still work (using in-memory cache)
        $dto2 = $this->hydrator->hydrate(['name' => 'Jane', 'age' => 25], SimpleDTO::class);

        $this->assertSame('Jane', $dto2->name);
    }

    public function testBypassCacheSkipsInMemory(): void
    {
        // First hydration - populates caches
        $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);

        $initialFileCount = count(glob(self::$cacheDir.'/hydrator/*.php') ?: []);

        // Bypass should regenerate
        $this->hydrator->hydrate(
            ['name' => 'Jane', 'age' => 25],
            SimpleDTO::class,
            bypassCache: true
        );

        // File count should be same (overwritten, not added)
        $finalFileCount = count(glob(self::$cacheDir.'/hydrator/*.php') ?: []);
        $this->assertSame($initialFileCount, $finalFileCount);
    }

    /**
     * Populates all cache entry kinds: hydrator, simple array hydrator, discriminated array hydrator,
     * dehydrator and array dehydrator.
     */
    private function runAllCachedOperations(Hydrator $hydrator, Dehydrator $dehydrator): void
    {
        $dto = $hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);
        $this->assertSame('John', $dto->name);

        $list = $hydrator->hydrateArray([['name' => 'John', 'age' => 30]], className: SimpleDTO::class);
        $this->assertCount(1, $list);

        $discriminated = $hydrator->hydrateArray(
            [['type' => 'simple', 'name' => 'Jane', 'age' => 25]],
            discriminator: 'type',
            mapping: ['simple' => SimpleDTO::class],
        );
        $this->assertCount(1, $discriminated);

        $this->assertSame(['name' => 'John', 'age' => 30], $dehydrator->dehydrate(new SimpleDTO('John', 30)));
        $this->assertSame([['name' => 'John', 'age' => 30]], $dehydrator->dehydrateArray([new SimpleDTO('John', 30)]));
    }

    /**
     * @return list<string>
     */
    private function cacheFiles(): array
    {
        return glob(self::$cacheDir.'/*/*.php') ?: [];
    }
}
