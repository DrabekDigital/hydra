<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra;

use DrabekDigital\Hydra\Attributes\CustomFormatter;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Attributes\MapperAttribute;
use DrabekDigital\Hydra\Attributes\ObjectType;
use DrabekDigital\Hydra\Caching\CacheInterface;
use DrabekDigital\Hydra\CodeGen\ArrayHydratorGenerator;
use DrabekDigital\Hydra\CodeGen\HydratorGenerator;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Mappers\Mapper;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Runtime\ArrayHydratorRuntime;
use DrabekDigital\Hydra\Runtime\HydratorRuntime;

class Hydrator
{
    private const CACHE_TYPE = 'hydrator';
    private const CACHE_TYPE_ARRAY = 'array_hydrator';

    private readonly HydratorGenerator $generator;
    private readonly ArrayHydratorGenerator $arrayGenerator;
    private readonly ClassAnalyzer $analyzer;

    /** @var array<string, HydratorRuntime> */
    private array $loadedHydrators = [];

    /** @var array<string, ArrayHydratorRuntime> */
    private array $loadedArrayHydrators = [];

    /** @var array<string, Mapper> */
    private array $customMappers = [];

    /** @var array<string, string> Structure hash cache */
    private array $structureHashes = [];

    /** @var array<string, list<string>> */
    private array $arrayCacheKeysByClass = [];

    public function __construct(
        private readonly CacheInterface $cache,
        ?ClassAnalyzer $analyzer = null,
        ?HydratorGenerator $generator = null,
    ) {
        $this->analyzer = $analyzer ?? new ClassAnalyzer();
        $this->generator = $generator ?? new HydratorGenerator($this->analyzer);
        $this->arrayGenerator = new ArrayHydratorGenerator();
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
     * Hydrate an array into an object of the given class.
     *
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $className
     * @param bool                 $bypassCache Skip cache and regenerate hydrator
     *
     * @return T
     *
     * @throws HydrationFailure          When input data cannot be hydrated into requested object
     * @throws \InvalidArgumentException When DTO metadata/attributes contain invalid configuration
     * @throws \RuntimeException         When runtime code generation/cache/bootstrap fails
     */
    public function hydrate(array $data, string $className, bool $bypassCache = false): object
    {
        $hydrator = $this->getHydrator($className, $bypassCache);
        $result = $hydrator->hydrate($data);
        if (!$result instanceof $className) {
            throw new \RuntimeException("Hydrator returned unexpected type for {$className}");
        }

        return $result;
    }

    /**
     * Hydrate an array of data into an array of objects.
     *
     * @param array<int, mixed>                $data          Array of data arrays
     * @param null|class-string                $className     Target class (required if no discriminator)
     * @param null|string                      $discriminator Field name to determine type
     * @param null|array<string, class-string> $mapping       Map of discriminator values to class names
     * @param bool                             $bypassCache   Skip cache and regenerate hydrators
     *
     * @return array<object>
     *
     * @throws HydrationFailure          When one or more items cannot be hydrated
     * @throws \InvalidArgumentException When arguments or DTO metadata/attributes are invalid
     * @throws \RuntimeException         When runtime code generation/cache/bootstrap fails
     */
    public function hydrateArray(
        array $data,
        ?string $className = null,
        ?string $discriminator = null,
        ?array $mapping = null,
        bool $bypassCache = false,
    ): array {
        if ($discriminator !== null && $mapping !== null) {
            $arrayHydrator = $this->getDiscriminatedArrayHydrator($discriminator, $mapping, $bypassCache);
        } elseif ($className !== null) {
            $arrayHydrator = $this->getSimpleArrayHydrator($className, $bypassCache);
        } else {
            throw new \InvalidArgumentException('Either className or discriminator+mapping must be provided');
        }

        return $arrayHydrator->hydrate($data);
    }

    /**
     * Clear the cache for a specific class or all classes.
     */
    public function clearCache(?string $className = null): void
    {
        if ($className !== null) {
            $cacheKey = "{$className}_{$this->getStructureHash($className)}";
            $this->cache->invalidate($cacheKey, self::CACHE_TYPE);
            unset($this->loadedHydrators[$cacheKey], $this->structureHashes[$className]);

            $simpleArrayKey = "simple_{$className}_{$this->analyzer->getStructureHash($className)}";
            $this->cache->invalidate($simpleArrayKey, self::CACHE_TYPE_ARRAY);
            unset($this->loadedArrayHydrators[$simpleArrayKey]);

            foreach ($this->arrayCacheKeysByClass[$className] ?? [] as $arrayCacheKey) {
                $this->cache->invalidate($arrayCacheKey, self::CACHE_TYPE_ARRAY);
                unset($this->loadedArrayHydrators[$arrayCacheKey]);
            }
            unset($this->arrayCacheKeysByClass[$className]);
        } else {
            $this->cache->clear();
            $this->loadedHydrators = [];
            $this->loadedArrayHydrators = [];
            $this->structureHashes = [];
            $this->arrayCacheKeysByClass = [];
        }
    }

    /**
     * @param class-string $className
     */
    private function getSimpleArrayHydrator(string $className, bool $bypassCache = false): ArrayHydratorRuntime
    {
        $structureHash = $this->getStructureHash($className);
        $cacheKey = "simple_{$className}_{$structureHash}";
        $this->registerArrayCacheKey($cacheKey, [$className]);

        if (!$bypassCache && isset($this->loadedArrayHydrators[$cacheKey])) {
            return $this->loadedArrayHydrators[$cacheKey];
        }

        if (!$bypassCache && $this->cache->has($cacheKey, self::CACHE_TYPE_ARRAY)) {
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        } else {
            $code = $this->arrayGenerator->generateSimple($className);
            $this->cache->set($cacheKey, self::CACHE_TYPE_ARRAY, $code);
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        }
        if (!$hydrator instanceof ArrayHydratorRuntime) {
            throw new \RuntimeException("Invalid cached array hydrator for {$className}");
        }

        // Register nested hydrator for the item class
        $nestedHydrator = $this->getHydrator($className, $bypassCache);
        $hydrator->setNestedHydrator($className, $nestedHydrator);

        $this->loadedArrayHydrators[$cacheKey] = $hydrator;

        return $hydrator;
    }

    /**
     * @param array<string, class-string> $mapping
     */
    private function getDiscriminatedArrayHydrator(string $discriminator, array $mapping, bool $bypassCache = false): ArrayHydratorRuntime
    {
        // Build cache key including structure hashes of all mapped classes
        $mappingHashes = [];
        foreach ($mapping as $value => $class) {
            $mappingHashes[$value] = $class.'_'.$this->getStructureHash($class);
        }
        $cacheKey = "disc_{$discriminator}_".md5(serialize($mappingHashes));
        $this->registerArrayCacheKey($cacheKey, array_values($mapping));

        if (!$bypassCache && isset($this->loadedArrayHydrators[$cacheKey])) {
            return $this->loadedArrayHydrators[$cacheKey];
        }

        if (!$bypassCache && $this->cache->has($cacheKey, self::CACHE_TYPE_ARRAY)) {
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        } else {
            $code = $this->arrayGenerator->generateDiscriminated($discriminator, $mapping);
            $this->cache->set($cacheKey, self::CACHE_TYPE_ARRAY, $code);
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE_ARRAY);
        }
        if (!$hydrator instanceof ArrayHydratorRuntime) {
            throw new \RuntimeException('Invalid cached discriminated array hydrator');
        }

        // Register nested hydrators for all mapped classes
        foreach ($mapping as $class) {
            $nestedHydrator = $this->getHydrator($class, $bypassCache);
            $hydrator->setNestedHydrator($class, $nestedHydrator);
        }

        $this->loadedArrayHydrators[$cacheKey] = $hydrator;

        return $hydrator;
    }

    private function getHydrator(string $className, bool $bypassCache = false): HydratorRuntime
    {
        $structureHash = $this->getStructureHash($className);
        $cacheKey = "{$className}_{$structureHash}";

        if (!$bypassCache && isset($this->loadedHydrators[$cacheKey])) {
            return $this->loadedHydrators[$cacheKey];
        }

        if (!$bypassCache && $this->cache->has($cacheKey, self::CACHE_TYPE)) {
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE);
        } else {
            if (!class_exists($className)) {
                throw new \RuntimeException("Class {$className} does not exist");
            }
            $code = $this->generator->generate($className);
            $this->cache->set($cacheKey, self::CACHE_TYPE, $code);
            $hydrator = $this->cache->get($cacheKey, self::CACHE_TYPE);
        }
        if (!$hydrator instanceof HydratorRuntime) {
            throw new \RuntimeException("Invalid cached hydrator for {$className}");
        }

        // Register nested hydrators
        $this->registerNestedHydrators($hydrator, $className, $bypassCache);

        $this->loadedHydrators[$cacheKey] = $hydrator;

        return $hydrator;
    }

    private function getStructureHash(string $className): string
    {
        if (!isset($this->structureHashes[$className])) {
            $this->structureHashes[$className] = $this->analyzer->getStructureHash($className);
        }

        return $this->structureHashes[$className];
    }

    private function registerNestedHydrators(HydratorRuntime $hydrator, string $className, bool $bypassCache = false): void
    {
        $properties = $this->analyzer->analyze($className);

        foreach ($properties as $property) {
            // Check for direct object type
            $primaryType = $property->getPrimaryType();
            if ($primaryType !== null && $this->analyzer->isObjectType($primaryType)) {
                $nestedHydrator = $this->getHydrator($primaryType, $bypassCache);
                $hydrator->setNestedHydrator($primaryType, $nestedHydrator);
            }

            // Check for ListOf attribute
            $listOf = $property->getAttribute(ListOf::class);
            if ($listOf !== null && $this->analyzer->isObjectType($listOf->value)) {
                $nestedHydrator = $this->getHydrator($listOf->value, $bypassCache);
                $hydrator->setNestedHydrator($listOf->value, $nestedHydrator);
            }
            if ($listOf !== null && $listOf->mapping !== null) {
                foreach ($listOf->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedHydrator = $this->getHydrator($mappedClass, $bypassCache);
                    $hydrator->setNestedHydrator($mappedClass, $nestedHydrator);
                }
            }

            $mapOf = $property->getAttribute(MapOf::class);
            if ($mapOf !== null && $this->analyzer->isObjectType($mapOf->value)) {
                $nestedHydrator = $this->getHydrator($mapOf->value, $bypassCache);
                $hydrator->setNestedHydrator($mapOf->value, $nestedHydrator);
            }
            if ($mapOf !== null && $mapOf->mapping !== null) {
                foreach ($mapOf->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedHydrator = $this->getHydrator($mappedClass, $bypassCache);
                    $hydrator->setNestedHydrator($mappedClass, $nestedHydrator);
                }
            }

            $objectType = $property->getAttribute(ObjectType::class);
            if ($objectType !== null && $objectType->value !== null && $this->analyzer->isObjectType($objectType->value)) {
                $nestedHydrator = $this->getHydrator($objectType->value, $bypassCache);
                $hydrator->setNestedHydrator($objectType->value, $nestedHydrator);
            }
            if ($objectType !== null && $objectType->mapping !== null) {
                foreach ($objectType->mapping as $mappedClass) {
                    if (!class_exists($mappedClass)) {
                        throw new \RuntimeException("Mapped class {$mappedClass} does not exist");
                    }
                    $nestedHydrator = $this->getHydrator($mappedClass, $bypassCache);
                    $hydrator->setNestedHydrator($mappedClass, $nestedHydrator);
                }
            }

            // Check for custom mapper attributes
            foreach ($property->attributes as $attr) {
                if ($attr instanceof MapperAttribute) {
                    $mapperClass = $attr->getMapperClass();
                    $mapper = $this->getMapper($mapperClass);
                    $hydrator->setCustomMapper($mapperClass, $mapper);
                }
            }

            // Check for CustomFormatter
            $formatter = $property->getAttribute(CustomFormatter::class);
            if ($formatter !== null) {
                $hydrator->setFormatter($property->name, $formatter->hydrate, $formatter->dehydrate);
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
