<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Metadata;

use DrabekDigital\Hydra\Attributes\DefaultValue;
use DrabekDigital\Hydra\Attributes\Undefinable;
use DrabekDigital\Hydra\Attributes\UndefinedDehydration;

class ClassAnalyzer
{
    /**
     * @return array<PropertyMetadata>
     */
    public function analyze(string $className): array
    {
        /** @var class-string $className */
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        // If no constructor or constructor has no parameters, analyze public properties
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $this->analyzeProperties($reflection);
        }

        $properties = [];
        foreach ($constructor->getParameters() as $parameter) {
            $properties[] = $this->analyzeParameter($parameter);
        }

        return $properties;
    }

    public function isObjectType(string $typeName): bool
    {
        return !in_array($typeName, ['string', 'int', 'float', 'bool', 'array', 'mixed', 'null', 'callable', 'iterable', 'object'], true)
            && class_exists($typeName);
    }

    /**
     * Generate a hash representing the structure of a class.
     *
     * This hash changes when:
     * - Properties are added, removed, or reordered
     * - Property types change
     * - Attributes on properties change
     * - Default values change
     */
    public function getStructureHash(string $className): string
    {
        /** @var class-string $className */
        $reflection = new \ReflectionClass($className);
        $structure = [
            'class' => $className,
            'classAttributes' => [],
            'properties' => [],
        ];

        foreach ($reflection->getAttributes() as $classAttribute) {
            $attributeInstance = $classAttribute->newInstance();
            $structure['classAttributes'][$classAttribute->getName()] = $this->serializeValue(
                $this->extractAttributeData($attributeInstance)
            );
        }

        $constructor = $reflection->getConstructor();

        // Use constructor parameters if available, otherwise use public properties
        if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
            foreach ($constructor->getParameters() as $parameter) {
                $paramStructure = [
                    'name' => $parameter->getName(),
                    'type' => $this->getTypeSignature($parameter->getType()),
                    'hasDefault' => $parameter->isDefaultValueAvailable(),
                    'attributes' => [],
                ];

                if ($parameter->isDefaultValueAvailable()) {
                    $paramStructure['default'] = $this->serializeValue($parameter->getDefaultValue());
                }

                // Include attribute data in the hash
                foreach ($parameter->getAttributes() as $attr) {
                    $attrInstance = $attr->newInstance();
                    $paramStructure['attributes'][$attr->getName()] = $this->serializeValue(
                        $this->extractAttributeData($attrInstance)
                    );
                }

                $structure['properties'][] = $paramStructure;
            }
        } else {
            // Analyze public properties for DTOs without constructor parameters
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                $propStructure = [
                    'name' => $property->getName(),
                    'type' => $this->getTypeSignature($property->getType()),
                    'hasDefault' => $property->hasDefaultValue(),
                    'attributes' => [],
                ];

                if ($property->hasDefaultValue()) {
                    $propStructure['default'] = $this->serializeValue($property->getDefaultValue());
                }

                foreach ($property->getAttributes() as $attr) {
                    $attrInstance = $attr->newInstance();
                    $propStructure['attributes'][$attr->getName()] = $this->serializeValue(
                        $this->extractAttributeData($attrInstance)
                    );
                }

                $structure['properties'][] = $propStructure;
            }
        }

        $json = json_encode($structure);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode structure for {$className}");
        }

        return substr(md5($json), 0, 12);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     *
     * @return array<PropertyMetadata>
     */
    private function analyzeProperties(\ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $properties[] = $this->analyzeProperty($property);
        }

        return $properties;
    }

    private function analyzeProperty(\ReflectionProperty $property): PropertyMetadata
    {
        $type = $property->getType();
        $types = [];
        $isNullable = false;
        $isBuiltin = false;

        if ($type instanceof \ReflectionNamedType) {
            $types[] = $type->getName();
            $isNullable = $type->allowsNull();
            $isBuiltin = $type->isBuiltin();
        } elseif ($type instanceof \ReflectionUnionType) {
            $allBuiltin = true;
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType) {
                    $typeName = $unionType->getName();
                    if ($typeName === 'null') {
                        $isNullable = true;
                    } else {
                        $types[] = $typeName;
                        if (!$unionType->isBuiltin()) {
                            $allBuiltin = false;
                        }
                    }
                }
            }
            $isBuiltin = $allBuiltin && !empty($types);
        }

        $hasDefault = $property->hasDefaultValue();
        $defaultValue = $hasDefault ? $property->getDefaultValue() : null;

        // Collect all attributes
        $attributes = [];
        foreach ($property->getAttributes() as $attr) {
            $attributes[$attr->getName()] = $attr->newInstance();
        }

        // Check for #[DefaultValue] attribute
        $hasAttributeDefault = false;
        $attributeDefaultValue = null;
        if (isset($attributes[DefaultValue::class])) {
            $hasAttributeDefault = true;

            /** @var DefaultValue $defaultValueAttribute */
            $defaultValueAttribute = $attributes[DefaultValue::class];
            $attributeDefaultValue = $defaultValueAttribute->value;
        }

        // Check for #[Undefinable] attribute
        $isUndefinable = false;
        $undefinedDehydrationValue = null;
        $hasUndefinedDehydrationFallback = false;
        if (isset($attributes[Undefinable::class])) {
            $isUndefinable = true;

            /** @var Undefinable $undefinableAttribute */
            $undefinableAttribute = $attributes[Undefinable::class];
            $undefinedDehydrationValue = match ($undefinableAttribute->onUndefinedDehydration) {
                UndefinedDehydration::SKIP => null,
                UndefinedDehydration::AS_NULL => null,
                UndefinedDehydration::AS_VALUE => $undefinableAttribute->undefinedAs,
            };
            $hasUndefinedDehydrationFallback = $undefinableAttribute->onUndefinedDehydration !== UndefinedDehydration::SKIP;
        }

        return new PropertyMetadata(
            name: $property->getName(),
            types: $types,
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: $isBuiltin,
            hasAttributeDefault: $hasAttributeDefault,
            attributeDefaultValue: $attributeDefaultValue,
            isUndefinable: $isUndefinable,
            undefinedDehydrationValue: $undefinedDehydrationValue,
            hasUndefinedDehydrationFallback: $hasUndefinedDehydrationFallback,
            attributes: $attributes,
        );
    }

    private function analyzeParameter(\ReflectionParameter $parameter): PropertyMetadata
    {
        $type = $parameter->getType();
        $types = [];
        $isNullable = false;
        $isBuiltin = false;

        if ($type instanceof \ReflectionNamedType) {
            $types[] = $type->getName();
            $isNullable = $type->allowsNull();
            $isBuiltin = $type->isBuiltin();
        } elseif ($type instanceof \ReflectionUnionType) {
            $allBuiltin = true;
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType) {
                    $typeName = $unionType->getName();
                    if ($typeName === 'null') {
                        $isNullable = true;
                    } else {
                        $types[] = $typeName;
                        if (!$unionType->isBuiltin()) {
                            $allBuiltin = false;
                        }
                    }
                }
            }
            $isBuiltin = $allBuiltin && !empty($types);
        }

        $hasDefault = $parameter->isDefaultValueAvailable();
        $defaultValue = $hasDefault ? $parameter->getDefaultValue() : null;

        // Collect all attributes
        $attributes = [];
        foreach ($parameter->getAttributes() as $attr) {
            $attributes[$attr->getName()] = $attr->newInstance();
        }

        // Check for #[DefaultValue] attribute
        $hasAttributeDefault = false;
        $attributeDefaultValue = null;
        if (isset($attributes[DefaultValue::class])) {
            $hasAttributeDefault = true;

            /** @var DefaultValue $defaultValueAttribute */
            $defaultValueAttribute = $attributes[DefaultValue::class];
            $attributeDefaultValue = $defaultValueAttribute->value;
        }

        // Check for #[Undefinable] attribute
        $isUndefinable = false;
        $undefinedDehydrationValue = null;
        $hasUndefinedDehydrationFallback = false;
        if (isset($attributes[Undefinable::class])) {
            $isUndefinable = true;

            /** @var Undefinable $undefinableAttribute */
            $undefinableAttribute = $attributes[Undefinable::class];
            $undefinedDehydrationValue = match ($undefinableAttribute->onUndefinedDehydration) {
                UndefinedDehydration::SKIP => null,
                UndefinedDehydration::AS_NULL => null,
                UndefinedDehydration::AS_VALUE => $undefinableAttribute->undefinedAs,
            };
            $hasUndefinedDehydrationFallback = $undefinableAttribute->onUndefinedDehydration !== UndefinedDehydration::SKIP;
        }

        return new PropertyMetadata(
            name: $parameter->getName(),
            types: $types,
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: $isBuiltin,
            hasAttributeDefault: $hasAttributeDefault,
            attributeDefaultValue: $attributeDefaultValue,
            isUndefinable: $isUndefinable,
            undefinedDehydrationValue: $undefinedDehydrationValue,
            hasUndefinedDehydrationFallback: $hasUndefinedDehydrationFallback,
            attributes: $attributes,
        );
    }

    private function getTypeSignature(?\ReflectionType $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof \ReflectionNamedType) {
            return ($type->allowsNull() ? '?' : '').$type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof \ReflectionNamedType) {
                    $types[] = $unionType->getName();
                }
            }
            sort($types);

            return implode('|', $types);
        }

        return 'mixed';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractAttributeData(object $attribute): array
    {
        $data = ['class' => $attribute::class];
        $reflection = new \ReflectionClass($attribute);

        foreach ($reflection->getProperties() as $prop) {
            if ($prop->isInitialized($attribute)) {
                $value = $prop->getValue($attribute);
                // Handle closures by converting to a marker
                if ($value instanceof \Closure) {
                    $data[$prop->getName()] = '__closure__';
                } else {
                    $data[$prop->getName()] = $value;
                }
            }
        }

        return $data;
    }

    private function serializeValue(mixed $value): string
    {
        if (is_object($value)) {
            if ($value instanceof \BackedEnum) {
                return $value::class.'::'.$value->value;
            }

            return $value::class;
        }

        return json_encode($value) ?: 'null';
    }
}
