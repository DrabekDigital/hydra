<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Caching\FileCache;
use DrabekDigital\Hydra\CodeGen\GeneratorVersion;
use DrabekDigital\Hydra\Hydrator;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ListOfMissingMappedClassDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ObjectTypeMissingMappedClassDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ObjectTypeValueDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class HydratorCoverageTest extends TestCase
{
    public function testHydrateThrowsWhenCachedHydratorReturnsUnexpectedType(): void
    {
        $cache = new FileCache(self::$cacheDir);
        $className = SimpleDTO::class;
        $cacheKey = $this->hydratorCacheKey($className);

        $cache->set($cacheKey, 'hydrator', <<<'PHP'
            <?php
            declare(strict_types=1);

            use DrabekDigital\Hydra\Runtime\HydratorRuntime;

            return new class implements HydratorRuntime {
                public function hydrate(array $data, string $path = ''): object
                {
                    return new \stdClass();
                }

                public function setNestedHydrator(string $className, object $hydrator): void {}

                public function setCustomMapper(string $className, object $mapper): void {}

                public function setFormatter(string $propertyName, ?\Closure $hydrate, ?\Closure $dehydrate): void {}
            };
            PHP);

        $hydrator = new Hydrator($cache);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hydrator returned unexpected type');
        $hydrator->hydrate(['name' => 'John', 'age' => 30], $className);
    }

    public function testHydrateThrowsWhenCachedHydratorIsInvalidObject(): void
    {
        $cache = new FileCache(self::$cacheDir);
        $className = SimpleDTO::class;
        $cache->set($this->hydratorCacheKey($className), 'hydrator', '<?php return new \stdClass();');

        $hydrator = new Hydrator($cache);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid cached hydrator for {$className}");
        $hydrator->hydrate(['name' => 'John', 'age' => 30], $className);
    }

    public function testSimpleArrayHydratorUsesLoadedInstanceOnSecondCall(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $first = $this->hydrator->hydrateArray($data, className: SimpleDTO::class);
        $second = $this->hydrator->hydrateArray($data, className: SimpleDTO::class);

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
    }

    public function testSimpleArrayHydratorThrowsForInvalidCachedRuntime(): void
    {
        $cache = new FileCache(self::$cacheDir);
        $className = SimpleDTO::class;
        $cache->set($this->simpleArrayCacheKey($className), 'array_hydrator', '<?php return new \stdClass();');

        $hydrator = new Hydrator($cache);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid cached array hydrator for {$className}");
        $hydrator->hydrateArray([], className: $className);
    }

    public function testDiscriminatedArrayHydratorUsesLoadedInstanceOnSecondCall(): void
    {
        $mapping = ['car' => Car::class, 'motorcycle' => Motorcycle::class];
        $data = [
            ['type' => 'car', 'brand' => 'Skoda', 'year' => 2024, 'doors' => 5],
            ['type' => 'motorcycle', 'brand' => 'Honda', 'year' => 2020, 'engineCC' => 750],
        ];

        $first = $this->hydrator->hydrateArray($data, discriminator: 'type', mapping: $mapping);
        $second = $this->hydrator->hydrateArray($data, discriminator: 'type', mapping: $mapping);

        $this->assertCount(2, $first);
        $this->assertCount(2, $second);
    }

    public function testDiscriminatedArrayHydratorThrowsForInvalidCachedRuntime(): void
    {
        $cache = new FileCache(self::$cacheDir);
        $mapping = ['car' => Car::class, 'motorcycle' => Motorcycle::class];
        $cache->set($this->discriminatedArrayCacheKey('type', $mapping), 'array_hydrator', '<?php return new \stdClass();');

        $hydrator = new Hydrator($cache);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid cached discriminated array hydrator');
        $hydrator->hydrateArray([], discriminator: 'type', mapping: $mapping);
    }

    public function testHydrateThrowsWhenClassDoesNotExist(): void
    {
        $missingClass = 'DrabekDigital\Hydra\Tests\Fixtures\Dto\MissingDTO';
        $property = new \ReflectionProperty($this->hydrator, 'structureHashes');
        $property->setValue($this->hydrator, [$missingClass => 'manual_hash']);
        $method = new \ReflectionMethod($this->hydrator, 'getHydrator');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Class {$missingClass} does not exist");
        $method->invoke($this->hydrator, $missingClass, false);
    }

    public function testListOfMappingWithMissingClassThrowsDuringNestedRegistration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mapped class');
        $this->hydrator->hydrate(['vehicles' => []], ListOfMissingMappedClassDTO::class);
    }

    public function testObjectTypeMappingWithMissingClassThrowsDuringNestedRegistration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapped class Stringable does not exist');
        $this->hydrator->hydrate(
            ['featured' => ['type' => 'car', 'brand' => 'Skoda', 'year' => 2024, 'doors' => 5]],
            ObjectTypeMissingMappedClassDTO::class
        );
    }

    public function testObjectTypeValueAttributeHydratesNestedObject(): void
    {
        $dto = $this->hydrator->hydrate(
            ['address' => ['street' => 'Main', 'city' => 'Prague', 'zip' => '11000']],
            ObjectTypeValueDTO::class
        );

        $this->assertSame('Main', $dto->address->street);
    }

    public function testMapperThatDoesNotImplementInterfaceThrows(): void
    {
        $method = new \ReflectionMethod($this->hydrator, 'getMapper');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapper stdClass must implement');
        $method->invoke($this->hydrator, \stdClass::class);
    }

    private function hydratorCacheKey(string $className): string
    {
        $hash = new ClassAnalyzer()->getStructureHash($className);

        return GeneratorVersion::cacheKey("{$className}_{$hash}");
    }

    private function simpleArrayCacheKey(string $className): string
    {
        $hash = new ClassAnalyzer()->getStructureHash($className);

        return GeneratorVersion::cacheKey("simple_{$className}_{$hash}");
    }

    /**
     * @param array<string, class-string> $mapping
     */
    private function discriminatedArrayCacheKey(string $discriminator, array $mapping): string
    {
        $analyzer = new ClassAnalyzer();
        $mappingHashes = [];
        foreach ($mapping as $value => $class) {
            $mappingHashes[$value] = $class.'_'.$analyzer->getStructureHash($class);
        }

        return GeneratorVersion::cacheKey("disc_{$discriminator}_".md5(serialize($mappingHashes)));
    }
}
