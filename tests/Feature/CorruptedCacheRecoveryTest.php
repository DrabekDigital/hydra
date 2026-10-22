<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Caching\CacheInterface;
use DrabekDigital\Hydra\Caching\FileCache;
use DrabekDigital\Hydra\CodeGen\GeneratorVersion;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CorruptedCacheRecoveryTest extends TestCase
{
    private const string TRUNCATED_CODE = '<?php return new class implements \DrabekDigital\Hydra\Runtime\HydratorRuntime { public function hydrate(';

    public function testHydrateRegeneratesTruncatedCachedHydrator(): void
    {
        $path = $this->writeCorruptedEntry($this->versionedStructureKey(SimpleDTO::class), 'hydrator');

        $this->assertHydrates(['name' => 'John', 'age' => 30], SimpleDTO::class, function (SimpleDTO $dto): void {
            $this->assertSame('John', $dto->name);
            $this->assertSame(30, $dto->age);
        });
        $this->assertStringNotContainsString(self::TRUNCATED_CODE, (string) file_get_contents($path));
    }

    public function testHydrateRegeneratesCachedHydratorReturningNonObject(): void
    {
        $this->cache->set($this->versionedStructureKey(SimpleDTO::class), 'hydrator', '<?php return false;');

        $dto = $this->hydrator->hydrate(['name' => 'Jane', 'age' => 25], SimpleDTO::class);

        $this->assertSame('Jane', $dto->name);
    }

    public function testHydrateArrayRegeneratesTruncatedSimpleArrayHydrator(): void
    {
        $this->writeCorruptedEntry(GeneratorVersion::cacheKey('simple_'.$this->structureKey(SimpleDTO::class)), 'array_hydrator');

        $result = $this->hydrator->hydrateArray([['name' => 'John', 'age' => 30]], className: SimpleDTO::class);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(SimpleDTO::class, $result[0]);
        $this->assertSame('John', $result[0]->name);
    }

    public function testHydrateArrayRegeneratesTruncatedDiscriminatedArrayHydrator(): void
    {
        $mapping = ['car' => Car::class, 'motorcycle' => Motorcycle::class];
        $analyzer = new ClassAnalyzer();
        $mappingHashes = [];
        foreach ($mapping as $value => $class) {
            $mappingHashes[$value] = $class.'_'.$analyzer->getStructureHash($class);
        }
        $this->writeCorruptedEntry(GeneratorVersion::cacheKey('disc_type_'.md5(serialize($mappingHashes))), 'array_hydrator');

        $result = $this->hydrator->hydrateArray(
            [['type' => 'car', 'brand' => 'Skoda', 'year' => 2024, 'doors' => 5]],
            discriminator: 'type',
            mapping: $mapping,
        );

        $this->assertInstanceOf(Car::class, $result[0]);
    }

    public function testDehydrateRegeneratesTruncatedCachedDehydrator(): void
    {
        $this->writeCorruptedEntry($this->versionedStructureKey(SimpleDTO::class), 'dehydrator');

        $this->assertDehydrates(new SimpleDTO('John', 30), ['name' => 'John', 'age' => 30]);
    }

    public function testDehydrateArrayRegeneratesTruncatedArrayDehydrator(): void
    {
        $this->writeCorruptedEntry(GeneratorVersion::cacheKey('array_'.md5($this->structureKey(SimpleDTO::class))), 'array_dehydrator');

        $result = $this->dehydrator->dehydrateArray([new SimpleDTO('John', 30)]);

        $this->assertSame([['name' => 'John', 'age' => 30]], $result);
    }

    public function testHydrateThrowsWhenFreshlyGeneratedHydratorCannotBeLoaded(): void
    {
        $hydrator = new Hydrator($this->createNullReturningCache());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid cached hydrator for '.SimpleDTO::class);
        $hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);
    }

    public function testDehydrateThrowsWhenFreshlyGeneratedDehydratorCannotBeLoaded(): void
    {
        $dehydrator = new Dehydrator($this->createNullReturningCache());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid cached dehydrator for '.SimpleDTO::class);
        $dehydrator->dehydrate(new SimpleDTO('John', 30));
    }

    public function testCachedEntryIsLoadedWithoutRegeneration(): void
    {
        $this->hydrator->hydrate(['name' => 'John', 'age' => 30], SimpleDTO::class);

        $counting = new class(new FileCache(self::$cacheDir)) implements CacheInterface {
            public int $sets = 0;

            public function __construct(private readonly CacheInterface $inner) {}

            public function has(string $className, string $type): bool
            {
                return $this->inner->has($className, $type);
            }

            public function get(string $className, string $type): ?object
            {
                return $this->inner->get($className, $type);
            }

            public function set(string $className, string $type, string $code): void
            {
                ++$this->sets;
                $this->inner->set($className, $type, $code);
            }

            public function invalidate(string $className, string $type): void
            {
                $this->inner->invalidate($className, $type);
            }

            public function clear(): void
            {
                $this->inner->clear();
            }
        };

        new Hydrator($counting)->hydrate(['name' => 'Jane', 'age' => 25], SimpleDTO::class);

        $this->assertSame(0, $counting->sets);
    }

    private function createNullReturningCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public function has(string $className, string $type): bool
            {
                return false;
            }

            public function get(string $className, string $type): ?object
            {
                return null;
            }

            public function set(string $className, string $type, string $code): void {}

            public function invalidate(string $className, string $type): void {}

            public function clear(): void {}
        };
    }

    private function structureKey(string $className): string
    {
        return $className.'_'.new ClassAnalyzer()->getStructureHash($className);
    }

    private function versionedStructureKey(string $className): string
    {
        return GeneratorVersion::cacheKey($this->structureKey($className));
    }

    private function writeCorruptedEntry(string $cacheKey, string $type): string
    {
        $this->cache->set($cacheKey, $type, self::TRUNCATED_CODE);

        $files = glob(self::$cacheDir.'/'.$type.'/*.php') ?: [];
        $this->assertCount(1, $files);

        return $files[0];
    }
}
