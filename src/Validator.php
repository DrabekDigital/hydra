<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra;

use DrabekDigital\Hydra\Attributes\ArrayOf;
use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Attributes\MapOf;
use DrabekDigital\Hydra\Errors\ValidationError;
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

        foreach ($properties as $property) {
            if (!property_exists($object, $property->name)) {
                continue;
            }

            $value = $object->{$property->name};
            $fullPath = $path === '' ? $property->name : "{$path}.{$property->name}";

            array_push(
                $errors,
                ...$applicator->apply($property->name, $value, $fullPath, $property->attributes),
            );
            $this->validateNested($value, $property->attributes, $fullPath, $errors, $visited);
        }
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
