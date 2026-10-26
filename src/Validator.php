<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Errors\ValidationError;
use DrabekDigital\Hydra\Errors\ValidationErrorCode;
use DrabekDigital\Hydra\Exceptions\ValidationFailure;
use DrabekDigital\Hydra\Metadata\ClassAnalyzer;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Metadata\ValidationAttributeCompatibility;
use DrabekDigital\Hydra\Validators\AttributeValidator;
use DrabekDigital\Hydra\Validators\ValidationRuleApplicator;
use DrabekDigital\Hydra\Validators\Validator as ValidatorInterface;

class Validator
{
    /** @var array<class-string, AttributeValidator|ValidatorInterface> */
    private array $customValidators = [];

    /** @var array<string, true> */
    private array $compatibilityCheckedClasses = [];

    /** @var array<class-string, array<string, \ReflectionProperty>> */
    private array $reflectionProperties = [];

    public function __construct(
        private readonly ?ClassAnalyzer $analyzer = null,
    ) {}

    /**
     * @param class-string<AttributeValidator|ValidatorInterface> $validatorClass
     */
    public function registerValidator(string $validatorClass, AttributeValidator|ValidatorInterface $validator): self
    {
        $this->customValidators[$validatorClass] = $validator;

        return $this;
    }

    /**
     * @throws ValidationFailure
     */
    public function validate(object $object): void
    {
        $errors = [];

        /** @var \SplObjectStorage<object, bool> $visited */
        $visited = new \SplObjectStorage();
        $this->validateObject($object, '', $errors, $visited);

        if ($errors !== []) {
            throw new ValidationFailure($errors);
        }
    }

    /**
     * @param array<ValidationError>          $errors
     * @param \SplObjectStorage<object, bool> $visited
     */
    private function validateObject(object $object, string $path, array &$errors, \SplObjectStorage $visited): void
    {
        if ($visited->offsetExists($object)) {
            return;
        }
        $visited->offsetSet($object, true);

        $analyzer = $this->analyzer ?? new ClassAnalyzer();
        $properties = $analyzer->analyze($object::class);

        $this->ensureValidationCompatible($object::class, $properties);

        $applicator = new ValidationRuleApplicator($this->customValidators);

        $reflectionProperties = $this->getReflectionProperties($object::class, $properties);

        foreach ($properties as $property) {
            $reflectionProperty = $reflectionProperties[$property->name] ?? null;
            if ($reflectionProperty === null) {
                continue;
            }

            $fullPath = $path === '' ? $property->name : "{$path}.{$property->name}";

            if (!$reflectionProperty->isInitialized($object)) {
                // Undefined is not null: #[Undefinable] properties may be absent and are not validated.
                if (!$property->isUndefinable) {
                    $errors[] = new ValidationError(
                        ValidationErrorCode::NOT_INITIALIZED,
                        "Property '{$property->name}' is not initialized; mark it #[Undefinable] if it may be absent",
                        $property->name,
                        $fullPath,
                    );
                }

                continue;
            }

            $value = $reflectionProperty->getValue($object);

            array_push(
                $errors,
                ...$applicator->apply($property->name, $value, $fullPath, $property->attributes),
            );
            $this->validateNested($value, $property->attributes, $fullPath, $errors, $visited);
        }
    }

    /**
     * @param class-string            $class
     * @param array<PropertyMetadata> $properties
     *
     * @return array<string, \ReflectionProperty>
     */
    private function getReflectionProperties(string $class, array $properties): array
    {
        if (isset($this->reflectionProperties[$class])) {
            return $this->reflectionProperties[$class];
        }

        $reflection = new \ReflectionClass($class);
        $reflectionProperties = [];
        foreach ($properties as $property) {
            if (!$reflection->hasProperty($property->name)) {
                continue;
            }

            $reflectionProperty = $reflection->getProperty($property->name);
            if ($reflectionProperty->isStatic()) {
                continue;
            }

            $reflectionProperties[$property->name] = $reflectionProperty;
        }

        return $this->reflectionProperties[$class] = $reflectionProperties;
    }

    /**
     * @param array<PropertyMetadata> $properties
     */
    private function ensureValidationCompatible(string $class, array $properties): void
    {
        if (isset($this->compatibilityCheckedClasses[$class])) {
            return;
        }

        foreach ($properties as $property) {
            ValidationAttributeCompatibility::assertCompatible($property);
        }

        $this->compatibilityCheckedClasses[$class] = true;
    }

    /**
     * @param array<string, object>           $attributes
     * @param array<ValidationError>          $errors
     * @param \SplObjectStorage<object, bool> $visited
     */
    private function validateNested(mixed $value, array $attributes, string $fullPath, array &$errors, \SplObjectStorage $visited): void
    {
        if (is_object($value)) {
            $this->validateObject($value, $fullPath, $errors, $visited);

            return;
        }

        if (!is_array($value)) {
            return;
        }

        $listOf = $attributes[ListOf::class] ?? null;
        if ($listOf instanceof ListOf) {
            foreach ($value as $idx => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$idx}", $errors, $visited);
                }
            }
        }

        $arrayOf = $attributes[ArrayOf::class] ?? null;
        if ($arrayOf instanceof ArrayOf) {
            foreach ($value as $idx => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$idx}", $errors, $visited);
                }
            }
        }

        $mapOf = $attributes[MapOf::class] ?? null;
        if ($mapOf instanceof MapOf) {
            foreach ($value as $mapKey => $item) {
                if (is_object($item)) {
                    $this->validateObject($item, "{$fullPath}.{$mapKey}", $errors, $visited);
                }
            }
        }
    }
}
