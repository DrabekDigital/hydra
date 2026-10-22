<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Caching\CacheInterface;
use DrabekDigital\Hydra\CodeGen\ArrayDehydratorGenerator;
use DrabekDigital\Hydra\CodeGen\DehydratorGenerator;
use DrabekDigital\Hydra\CodeGen\GeneratorVersion;
use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Mappers\Mapper;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Runtime\ArrayDehydratorRuntime;
use DrabekDigital\Hydra\Runtime\DehydratorRuntime;

class Dehydrator
{
    private const CACHE_TYPE = 'dehydrator';
    private const CACHE_TYPE_ARRAY = 'array_dehydrator';

    private readonly DehydratorGenerator $generator;
    private readonly ArrayDehydratorGenerator $arrayGenerator;
    private readonly ClassAnalyzer $analyzer;
    private readonly string $generatorVersion;

    /** @var array<string, DehydratorRuntime> */
    private array $loadedDehydrators = [];

    /** @var array<string, ArrayDehydratorRuntime> */
    private array $loadedArrayDehydrators = [];

    /** @var array<string, Mapper> */
    private array $customMappers = [];

    /** @var array<string, string> Structure hash cache */
    private array $structureHashes = [];

    /** @var array<string, list<string>> */
    private array $arrayCacheKeysByClass = [];

    public function __construct(
        private readonly CacheInterface $cache,
        ?ClassAnalyzer $analyzer = null,
        ?DehydratorGenerator $generator = null,
        ?string $generatorVersion = null,
    ) {
        $this->generatorVersion = $generatorVersion ?? GeneratorVersion::GENERATOR_VERSION;
        GeneratorVersion::assertValid($this->generatorVersion);
        $this->analyzer = $analyzer ?? new ClassAnalyzer();
        $this->generator = $generator ?? new DehydratorGenerator($this->analyzer);
        $this->arrayGenerator = new ArrayDehydratorGenerator();
    }

    /**
     * Register a custom mapper instance.
     *
     * @param class-string<Mapper> $mapperClass
     */
    public function registerMapper(string $mapperClass, Mapper $mapper): self
    {
        $this->customMappers[$mapperClass] = $mapper;

        return $this;
    }

    /**
     * Dehydrate an object into an array.
     *
     * @param bool $bypassCache Skip cache and regenerate dehydrator
     *
     * @return array<string, mixed>
     *
     * @throws DehydrationFailure        When object cannot be dehydrated
     * @throws \InvalidArgumentException When DTO metadata/attributes contain invalid configuration
     * @throws \RuntimeException         When runtime code generation/cache/bootstrap fails
     */
    public function dehydrate(object $object, bool $bypassCache = false): array
    {
        $className = $object::class;
        $dehydrator = $this->getDehydrator($className, $bypassCache);

        return $dehydrator->dehydrate($object);
    }

    /**
     * Dehydrate an array of objects into an array of arrays.
     *
     * @param array<object> $objects     Array of objects to dehydrate
     * @param bool          $bypassCache Skip cache and regenerate dehydrators
     *
     * @return array<array<string, mixed>>
     *
     * @throws DehydrationFailure        When one or more objects cannot be dehydrated
     * @throws \InvalidArgumentException When DTO metadata/attributes contain invalid configuration
     * @throws \RuntimeException         When runtime code generation/cache/bootstrap fails
     */
    public function dehydrateArray(array $objects, bool $bypassCache = false): array
    {
        if (empty($objects)) {
            return [];
        }

        // Collect all unique classes
        $classes = [];
        foreach ($objects as $object) {
            $class = $object::class;
            if (!in_array($class, $classes, true)) {
                $classes[] = $class;
            }
        }

        $arrayDehydrator = $this->getArrayDehydrator($classes, $bypassCache);

        return $arrayDehydrator->dehydrate($objects);
    }

    /**
     * Clear the cache for a specific class or all classes.
     */
    public function clearCache(?string $className = null): void
    {
        if ($className !== null) {
            $cacheKey = $this->classCacheKey($className);
            $this->cache->invalidate($cacheKey, self::CACHE_TYPE);
            unset($this->loadedDehydrators[$cacheKey], $this->structureHashes[$className]);

            foreach ($this->arrayCacheKeysByClass[$className] ?? [] as $arrayCacheKey) {
                $this->cache->invalidate($arrayCacheKey, self::CACHE_TYPE_ARRAY);
                unset($this->loadedArrayDehydrators[$arrayCacheKey]);
            }
            unset($this->arrayCacheKeysByClass[$className]);
        } else {
            $this->cache->clear();
            $this->loadedDehydrators = [];
            $this->loadedArrayDehydrators = [];
            $this->structureHashes = [];
            $this->arrayCacheKeysByClass = [];
        }
    }

    /**
     * @param class-string[] $classes
     */
    private function getArrayDehydrator(array $classes, bool $bypassCache = false): ArrayDehydratorRuntime
    {
        // Build cache key including structure hashes of all classes
        sort($classes);
        $classHashes = [];
        foreach ($classes as $class) {
            $classHashes[] = $class.'_'.$this->getStructureHash($class);
        }
        $cacheKey = $this->versionedCacheKey('array_'.md5(implode('|', $classHashes)));
        $this->registerArrayCacheKey($cacheKey, $classes);

        if (!$bypassCache && isset($this->loadedArrayDehydrators[$cacheKey])) {
            return $this->loadedArrayDehydrators[$cacheKey];
        }

        if (!$bypassCache && $this->cache->has($cacheKey, self::CACHE_TYPE_ARRAY)) {
            $dehydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        } else {
            $code = $this->arrayGenerator->generate($classes);
            $this->cache->set($cacheKey, self::CACHE_TYPE_ARRAY, $code);
            $dehydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        }
        if (!$dehydrator instanceof ArrayDehydratorRuntime) {
            throw new \RuntimeException('Invalid cached array dehydrator');
        }

        // Register nested dehydrators for all classes
        foreach ($classes as $class) {
            $nestedDehydrator = $this->getDehydrator($class, $bypassCache);
            $dehydrator->setNestedDehydrator($class, $nestedDehydrator);
        }

        $this->loadedArrayDehydrators[$cacheKey] = $dehydrator;

        return $dehydrator;
    }

    private function getDehydrator(string $className, bool $bypassCache = false): DehydratorRuntime
    {
        $cacheKey = $this->classCacheKey($className);

        if (!$bypassCache && isset($this->loadedDehydrators[$cacheKey])) {
            return $this->loadedDehydrators[$cacheKey];
        }

        if (!$bypassCache && $this->cache->has($cacheKey, self::CACHE_TYPE)) {
            $dehydrator = $this->cache->get($cacheKey, self::CACHE_TYPE);
        } else {
            if (!class_exists($className)) {
                throw new \RuntimeException("Class {$className} does not exist");
            }
            $code = $this->generator->generate($className);
            $this->cache->set($cacheKey, self::CACHE_TYPE, $code);
            $dehydrator = $this->cache->get($cacheKey, self::CACHE_TYPE);
        }
        if (!$dehydrator instanceof DehydratorRuntime) {
            throw new \RuntimeException("Invalid cached dehydrator for {$className}");
        }

        // Register nested dehydrators
        $this->registerNestedDehydrators($dehydrator, $className, $bypassCache);

        $this->loadedDehydrators[$cacheKey] = $dehydrator;

        return $dehydrator;
    }

    private function classCacheKey(string $className): string
    {
        return $this->versionedCacheKey("{$className}_{$this->getStructureHash($className)}");
    }

    private function versionedCacheKey(string $key): string
    {
        return GeneratorVersion::cacheKey($key, $this->generatorVersion);
    }

    private function getStructureHash(string $className): string
    {
        if (!isset($this->structureHashes[$className])) {
            $this->structureHashes[$className] = $this->analyzer->getStructureHash($className);
        }

        return $this->structureHashes[$className];
    }

    private function registerNestedDehydrators(DehydratorRuntime $dehydrator, string $className, bool $bypassCache = false): void
    {
        $properties = $this->analyzer->analyze($className);

        foreach ($properties as $property) {
            // Check for direct object type
            $primaryType = $property->getPrimaryType();
            if ($primaryType !== null && $this->analyzer->isObjectType($primaryType)) {
                $nestedDehydrator = $this->getDehydrator($primaryType, $bypassCache);
                $dehydrator->setNestedDehydrator($primaryType, $nestedDehydrator);
            }

            // Check for ListOf attribute
            $listOf = $property->getAttribute(ListOf::class);
            if ($listOf !== null && $this->analyzer->isObjectType($listOf->value)) {
                $nestedDehydrator = $this->getDehydrator($listOf->value, $bypassCache);
                $dehydrator->setNestedDehydrator($listOf->value, $nestedDehydrator);
            }
            if ($listOf !== null && $listOf->mapping !== null) {
                foreach ($listOf->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedDehydrator = $this->getDehydrator($mappedClass, $bypassCache);
                    $dehydrator->setNestedDehydrator($mappedClass, $nestedDehydrator);
                }
            }

            $mapOf = $property->getAttribute(MapOf::class);
            if ($mapOf !== null && $this->analyzer->isObjectType($mapOf->value)) {
                $nestedDehydrator = $this->getDehydrator($mapOf->value, $bypassCache);
                $dehydrator->setNestedDehydrator($mapOf->value, $nestedDehydrator);
            }
            if ($mapOf !== null && $mapOf->mapping !== null) {
                foreach ($mapOf->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedDehydrator = $this->getDehydrator($mappedClass, $bypassCache);
                    $dehydrator->setNestedDehydrator($mappedClass, $nestedDehydrator);
                }
            }

            $objectType = $property->getAttribute(ObjectType::class);
            if ($objectType !== null && $objectType->value !== null && $this->analyzer->isObjectType($objectType->value)) {
                $nestedDehydrator = $this->getDehydrator($objectType->value, $bypassCache);
                $dehydrator->setNestedDehydrator($objectType->value, $nestedDehydrator);
            }
            if ($objectType !== null && $objectType->mapping !== null) {
                foreach ($objectType->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedDehydrator = $this->getDehydrator($mappedClass, $bypassCache);
                    $dehydrator->setNestedDehydrator($mappedClass, $nestedDehydrator);
                }
            }

            // Check for custom mapper attributes
            foreach ($property->attributes as $attr) {
                if ($attr instanceof MapperAttribute) {
                    $mapperClass = $attr->getMapperClass();
                    $mapper = $this->getMapper($mapperClass);
                    $dehydrator->setCustomMapper($mapperClass, $mapper);
                }
            }

            // Check for CustomFormatter
            $formatter = $property->getAttribute(CustomFormatter::class);
            if ($formatter !== null) {
                $dehydrator->setFormatter($property->name, $formatter->hydrate, $formatter->dehydrate);
            }
        }
    }

    private function getMapper(string $mapperClass): Mapper
    {
        if (isset($this->customMappers[$mapperClass])) {
            return $this->customMappers[$mapperClass];
        }

        // Auto-instantiate if not registered
        $mapper = new $mapperClass();
        if (!$mapper instanceof Mapper) {
            throw new \RuntimeException("Mapper {$mapperClass} must implement ".Mapper::class);
        }
        $this->customMappers[$mapperClass] = $mapper;

        return $mapper;
    }

    /**
     * @param list<class-string> $classes
     */
    private function registerArrayCacheKey(string $cacheKey, array $classes): void
    {
        foreach ($classes as $className) {
            if (!isset($this->arrayCacheKeysByClass[$className])) {
                $this->arrayCacheKeysByClass[$className] = [];
            }
            if (!in_array($cacheKey, $this->arrayCacheKeysByClass[$className], true)) {
                $this->arrayCacheKeysByClass[$className][] = $cacheKey;
            }
        }
    }
}
