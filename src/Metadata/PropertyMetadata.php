<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Metadata;

readonly class PropertyMetadata
{
    /**
     * @param array<string>         $types
     * @param array<string, object> $attributes Map of attribute class name => instance
     */
    public function __construct(
        public string $name,
        public array $types,
        public bool $isNullable,
        public bool $hasDefault,
        public mixed $defaultValue,
        public bool $isBuiltin,
        public bool $hasAttributeDefault = false,
        public mixed $attributeDefaultValue = null,
        public bool $isUndefinable = false,
        public mixed $undefinableOnDehydrationWhen = null,
        public array $attributes = [],
    ) {}

    /**
     * Whether the field is truly required (must be present in input).
     */
    public function isRequired(): bool
    {
        // Required if: not nullable, no PHP default, no attribute default, not undefinable
        return !$this->isNullable
            && !$this->hasDefault
            && !$this->hasAttributeDefault
            && !$this->isUndefinable;
    }

    /**
     * Whether the field has any kind of default (PHP or attribute).
     */
    public function hasAnyDefault(): bool
    {
        return $this->hasDefault || $this->hasAttributeDefault;
    }

    /**
     * Get the effective default value (attribute takes precedence).
     */
    public function getEffectiveDefault(): mixed
    {
        if ($this->hasAttributeDefault) {
            return $this->attributeDefaultValue;
        }

        return $this->defaultValue;
    }

    public function getPrimaryType(): ?string
    {
        return $this->types[0] ?? null;
    }

    /**
     * Check if property has a specific attribute.
     *
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     */
    public function hasAttribute(string $attributeClass): bool
    {
        return isset($this->attributes[$attributeClass]);
    }

    /**
     * Get a specific attribute instance.
     *
     * @template T of object
     *
     * @param class-string<T> $attributeClass
     *
     * @return null|T
     */
    public function getAttribute(string $attributeClass): ?object
    {
        $attribute = $this->attributes[$attributeClass] ?? null;
        if ($attribute !== null && !$attribute instanceof $attributeClass) {
            return null;
        }

        return $attribute;
    }
}
