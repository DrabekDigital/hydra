<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

use DrabekDigital\Hydra\Attributes\NamingStrategy;
use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\Skip;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\NamingStrategies\NamingStrategyInterface;

/**
 * Context passed through code generation to share state.
 */
final class GenerationContext
{
    /** @var array<string, bool> */
    private array $nestedTypes = [];
    private ?NamingStrategyInterface $namingStrategy = null;
    private bool $dehydrationErrorsGenerated = false;

    /**
     * @param array<PropertyMetadata> $properties
     */
    public function __construct(
        public readonly string $className,
        public readonly bool $hasUndefinable,
        public readonly array $properties,
        public readonly bool $isPropertyOnly = false,
        public readonly bool $allowExtraKeys = false,
    ) {
        /** @var class-string $className */
        $reflection = new \ReflectionClass($className);
        $attrs = $reflection->getAttributes(NamingStrategy::class);
        if ($attrs !== []) {
            /** @var NamingStrategy $attribute */
            $attribute = $attrs[0]->newInstance();
            $this->namingStrategy = $attribute->strategy;
        }
    }

    public function markDehydrationErrorsGenerated(): void
    {
        $this->dehydrationErrorsGenerated = true;
    }

    public function dehydrationErrorsGenerated(): bool
    {
        return $this->dehydrationErrorsGenerated;
    }

    /**
     * Register a nested object type that needs a hydrator/dehydrator.
     */
    public function registerNestedType(string $className): void
    {
        $this->nestedTypes[$className] = true;
    }

    /**
     * @return string[]
     */
    public function getNestedTypes(): array
    {
        return array_keys($this->nestedTypes);
    }

    /**
     * Get input key for a property (for future naming strategy support).
     */
    public function inputKey(string $propertyName): string
    {
        $property = $this->propertyByName($propertyName);
        $rename = $property?->getAttribute(Rename::class);
        if ($rename instanceof Rename && $rename->from !== null) {
            return $rename->from;
        }

        return $this->namingStrategy?->toInputKey($propertyName) ?? $propertyName;
    }

    /**
     * Get output key for a property (for future naming strategy support).
     */
    public function outputKey(string $propertyName): string
    {
        $property = $this->propertyByName($propertyName);
        $rename = $property?->getAttribute(Rename::class);
        if ($rename instanceof Rename && $rename->to !== null) {
            return $rename->to;
        }

        return $this->namingStrategy?->toOutputKey($propertyName) ?? $propertyName;
    }

    /**
     * @return string[]
     */
    public function inputKeys(): array
    {
        return array_map(
            fn (PropertyMetadata $property) => $this->inputKey($property->name),
            $this->properties
        );
    }

    public function shouldSkipHydration(PropertyMetadata $property): bool
    {
        $skip = $property->getAttribute(Skip::class);

        return $skip instanceof Skip && $skip->hydration;
    }

    public function shouldSkipDehydration(PropertyMetadata $property): bool
    {
        $skip = $property->getAttribute(Skip::class);

        return $skip instanceof Skip && $skip->dehydration;
    }

    private function propertyByName(string $propertyName): ?PropertyMetadata
    {
        foreach ($this->properties as $property) {
            if ($property->name === $propertyName) {
                return $property;
            }
        }

        return null;
    }
}
